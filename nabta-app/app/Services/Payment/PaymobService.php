<?php

namespace App\Services\Payment;

use App\Exceptions\PaymobException;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Paymob Egypt — the storefront's online payment gateway (cards and mobile
 * wallets such as Vodafone Cash and Orange Cash). The POS never uses this.
 *
 * Flow (Intention API + Unified Checkout):
 *   1. startCheckout() creates a payment intention for the order with the
 *      secret key and returns the Unified Checkout URL to send the customer to.
 *   2. Paymob calls back twice, independently:
 *        - the "processed" webhook, server to server (POST JSON), and
 *        - the "response" redirect, in the customer's browser (GET query).
 *      Both are signed with HMAC-SHA512 over the same 20 transaction fields;
 *      verify*() checks that signature. Nothing in either payload is trusted
 *      before it passes.
 *
 * Amounts go to Paymob in integer piastres ("cents").
 */
class PaymobService
{
    /**
     * The fields Paymob signs, in the order it concatenates them. Keys are the
     * webhook's `obj` paths; the browser redirect flattens them (see
     * responseCallbackValues()).
     */
    private const HMAC_FIELDS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured', // sic — Paymob's spelling
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    /** @var array<string, mixed> */
    private array $config;

    /** @param  array<string, mixed>|null  $config  defaults to config('services.paymob') */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('services.paymob', []);
    }

    /** Whether online payment can be offered at all. */
    public function isConfigured(): bool
    {
        return filled($this->config['secret_key'] ?? null)
            && filled($this->config['public_key'] ?? null)
            && filled($this->config['hmac_secret'] ?? null)
            && $this->integrationIds() !== [];
    }

    /** @return list<int> */
    public function integrationIds(): array
    {
        return array_values(array_map('intval', array_filter([
            $this->config['integration_id_card'] ?? null,
            $this->config['integration_id_wallet'] ?? null,
        ], fn ($id) => filled($id))));
    }

    /**
     * Creates the payment intention, records Paymob's identifiers on the order,
     * and returns the URL to redirect the customer to.
     *
     * @throws PaymobException
     */
    public function startCheckout(Order $order): string
    {
        $intention = $this->createIntention($order);

        $order->forceFill([
            'paymob_intention_id' => $intention['id'],
            'paymob_order_id' => $intention['order_id'],
        ])->save();

        return $this->checkoutUrl($intention['client_secret']);
    }

    /**
     * @return array{id: ?string, order_id: string, client_secret: string}
     *
     * @throws PaymobException
     */
    public function createIntention(Order $order): array
    {
        if (! $this->isConfigured()) {
            throw new PaymobException('Paymob is not configured.');
        }

        $order->loadMissing('items');

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders(['Authorization' => 'Token '.$this->config['secret_key']])
                ->acceptJson()
                ->asJson()
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->post('/v1/intention/', $this->intentionPayload($order));
        } catch (ConnectionException $e) {
            throw new PaymobException('Paymob unreachable: '.$e->getMessage(), previous: $e);
        }

        $clientSecret = $response->json('client_secret');
        $paymobOrderId = $response->json('intention_order_id');

        // The order id is required: it is the signed key that ties Paymob's
        // callbacks back to this order (OrderPaymentSettler::findOrder).
        if ($response->failed() || ! is_string($clientSecret) || $clientSecret === '' || blank($paymobOrderId)) {
            // The body can echo the billing data back, so only the status and
            // Paymob's own error fields are logged — never the whole response.
            throw new PaymobException(sprintf(
                'Paymob intention failed (HTTP %d): %s',
                $response->status(),
                json_encode($response->json('detail') ?? $response->json('message') ?? $response->reason(), JSON_UNESCAPED_UNICODE),
            ));
        }

        return [
            'id' => $response->json('id') !== null ? (string) $response->json('id') : null,
            'order_id' => (string) $paymobOrderId,
            'client_secret' => $clientSecret,
        ];
    }

    public function checkoutUrl(string $clientSecret): string
    {
        return $this->baseUrl().'/unifiedcheckout/?'.http_build_query([
            'publicKey' => $this->config['public_key'],
            'clientSecret' => $clientSecret,
        ]);
    }

    /**
     * The "transaction processed" webhook. `$hmac` comes from the query string,
     * the transaction from the JSON body's `obj`.
     *
     * @param  array<string, mixed>  $obj
     */
    public function verifyProcessedCallback(array $obj, mixed $hmac): bool
    {
        $values = array_map(fn (string $field) => data_get($obj, $field), self::HMAC_FIELDS);

        return $this->matches($values, $hmac);
    }

    /**
     * The "transaction response" browser redirect.
     *
     * @param  array<string, mixed>  $query  the request's query parameters
     */
    public function verifyResponseCallback(array $query): bool
    {
        return $this->matches($this->responseCallbackValues($query), $query['hmac'] ?? null);
    }

    /**
     * The redirect sends the same fields flattened into a query string. PHP
     * turns their dots into underscores, and the order id arrives as `order`
     * (older accounts) or `order_id` (Paymob's current docs).
     *
     * @param  array<string, mixed>  $query
     * @return list<mixed>
     */
    private function responseCallbackValues(array $query): array
    {
        return array_map(function (string $field) use ($query) {
            if ($field === 'order.id') {
                return $query['order'] ?? $query['order_id'] ?? null;
            }

            return $query[str_replace('.', '_', $field)] ?? null;
        }, self::HMAC_FIELDS);
    }

    /** @param  list<mixed>  $values */
    private function matches(array $values, mixed $hmac): bool
    {
        $secret = (string) ($this->config['hmac_secret'] ?? '');

        if ($secret === '' || ! is_string($hmac) || $hmac === '') {
            return false;
        }

        return hash_equals($this->sign($values, $secret), strtolower($hmac));
    }

    /** @param  list<mixed>  $values */
    public function sign(array $values, ?string $secret = null): string
    {
        // A non-scalar where Paymob sends a scalar can only be a forged or
        // malformed request; it becomes a value that will not match.
        $concatenated = implode('', array_map(fn ($v) => match (true) {
            $v === true => 'true',
            $v === false => 'false',
            is_scalar($v) => (string) $v,
            default => '',
        }, $values));

        return hash_hmac('sha512', $concatenated, $secret ?? (string) $this->config['hmac_secret']);
    }

    /**
     * The intention body. Every amount is recomputed from the stored order,
     * which was itself recomputed server-side by OrderPlacer.
     *
     * Each order line is sent as ONE item with quantity 1 and its line total as
     * the amount, plus shipping as its own item: that way the items always sum
     * to the intention amount exactly, whichever way Paymob multiplies.
     *
     * @return array<string, mixed>
     */
    private function intentionPayload(Order $order): array
    {
        $items = $order->items->map(fn ($item) => [
            'name' => mb_substr($item->name_ar, 0, 50),
            'amount' => (int) round((float) $item->line_total * 100),
            'description' => mb_substr($item->sku.' × '.$item->qty, 0, 255),
            'quantity' => 1,
        ])->all();

        $shippingCents = (int) round((float) $order->shipping_fee * 100);

        if ($shippingCents > 0) {
            $items[] = ['name' => 'الشحن', 'amount' => $shippingCents, 'description' => 'رسوم التوصيل', 'quantity' => 1];
        }

        [$first, $last] = $this->splitName($order->customer_name);

        // Paymob requires every billing field. We collect no email at checkout,
        // so the shop's own sender address stands in — receipts land with the
        // shop rather than bouncing off an invented address.
        $billing = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => (string) config('mail.from.address'),
            'phone_number' => $this->internationalPhone($order->customer_phone),
            'country' => 'EG',
            'state' => $order->governorate_name_snapshot,
            'city' => $order->city,
            'street' => mb_substr($order->address_line, 0, 255),
            'building' => 'NA',
            'floor' => 'NA',
            'apartment' => 'NA',
        ];

        return [
            'amount' => $order->totalInPiastres(),
            'currency' => $this->config['currency'] ?? 'EGP',
            'payment_methods' => $this->integrationIds(),
            'items' => $items,
            'billing_data' => $billing,
            'customer' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $billing['email'],
            ],
            // Comes back on every callback as merchant_order_id.
            'special_reference' => $order->order_number,
            'expiration' => max(60, (int) ($this->config['expires_after_minutes'] ?? 60) * 60),
            'notification_url' => route('paymob.webhook'),
            'redirection_url' => route('paymob.callback'),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return [$parts[0] ?? 'NA', $parts[1] ?? ($parts[0] ?? 'NA')];
    }

    /** 01012345678 -> +201012345678 */
    private function internationalPhone(string $phone): string
    {
        return str_starts_with($phone, '0') ? '+2'.$phone : $phone;
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://accept.paymob.com'), '/');
    }
}
