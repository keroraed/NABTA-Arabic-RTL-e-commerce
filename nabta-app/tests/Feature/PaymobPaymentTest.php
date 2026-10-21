<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Mail\AdminOrderPlacedNotification;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Payment\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymobPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const HMAC_SECRET = 'test-hmac-secret';

    /** What the fake Paymob API answers; a test can swap it before checkout. */
    private ?\Closure $paymobApi = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the "after response" email inline so assertions see it.
        $this->withoutDefer();

        config([
            'services.paymob' => [
                'base_url' => 'https://accept.paymob.com',
                'public_key' => 'egy_pk_test_123',
                'secret_key' => 'egy_sk_test_456',
                'hmac_secret' => self::HMAC_SECRET,
                'integration_id_card' => '111',
                'integration_id_wallet' => '222',
                'currency' => 'EGP',
                'expires_after_minutes' => 60,
                'timeout' => 5,
            ],
            'nabta.admin_notification_email' => 'shop@nabta.test',
        ]);

        Http::fake(fn () => $this->paymobApi
            ? ($this->paymobApi)()
            : Http::response([
                'id' => 'pi_test_abc',
                'intention_order_id' => 987654,
                'client_secret' => 'egy_csk_test_secret',
            ], 201));

        Mail::fake();
    }

    /* ---------------------------------------------------------------------
     | Helpers
     --------------------------------------------------------------------- */

    private function form(Governorate $governorate, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'سارة أحمد',
            'customer_phone' => '01012345678',
            'governorate_id' => $governorate->id,
            'city' => 'المعادي',
            'address_line' => 'شارع ٩، عقار ١٢، الدور الرابع',
            'payment_method' => 'paymob',
        ], $overrides);
    }

    /** Places a Paymob order through the real checkout and returns it. */
    private function placePaymobOrder(Product $product, int $qty = 2): Order
    {
        $governorate = Governorate::factory()->create(['shipping_fee' => 50]);

        $this->withSession(['cart' => [$product->id => $qty]])
            ->post(route('checkout.store'), $this->form($governorate))
            ->assertRedirect();

        return Order::sole();
    }

    /** A "transaction processed" `obj`, as Paymob posts it. */
    private function transaction(Order $order, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 192837,
            'pending' => false,
            'amount_cents' => $order->totalInPiastres(),
            'success' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'is_refunded' => false,
            'is_3d_secure' => true,
            'integration_id' => 111,
            'has_parent_transaction' => false,
            'order' => ['id' => 987654, 'merchant_order_id' => $order->order_number],
            'created_at' => '2026-10-19T14:05:11.123456',
            'currency' => 'EGP',
            'error_occured' => false,
            'owner' => 4242,
            'source_data' => ['pan' => '2346', 'type' => 'card', 'sub_type' => 'MasterCard'],
            'data' => ['message' => 'Approved'],
        ], $overrides);
    }

    /**
     * Paymob's documented HMAC, computed here independently of PaymobService:
     * the 20 fields in this exact order, booleans as "true"/"false", no
     * separator, HMAC-SHA512.
     */
    private function hmacFor(array $obj): string
    {
        $b = fn ($v) => $v ? 'true' : 'false';

        $string = $obj['amount_cents']
            .$obj['created_at']
            .$obj['currency']
            .$b($obj['error_occured'])
            .$b($obj['has_parent_transaction'])
            .$obj['id']
            .$obj['integration_id']
            .$b($obj['is_3d_secure'])
            .$b($obj['is_auth'])
            .$b($obj['is_capture'])
            .$b($obj['is_refunded'])
            .$b($obj['is_standalone_payment'])
            .$b($obj['is_voided'])
            .$obj['order']['id']
            .$obj['owner']
            .$b($obj['pending'])
            .$obj['source_data']['pan']
            .$obj['source_data']['sub_type']
            .$obj['source_data']['type']
            .$b($obj['success']);

        return hash_hmac('sha512', $string, self::HMAC_SECRET);
    }

    private function postWebhook(array $obj, ?string $hmac = null)
    {
        return $this->postJson(
            route('paymob.webhook', ['hmac' => $hmac ?? $this->hmacFor($obj)]),
            ['type' => 'TRANSACTION', 'obj' => $obj],
        );
    }

    /** The browser redirect: same fields, flattened, with Paymob's dotted keys. */
    private function redirectQuery(array $obj, ?string $hmac = null): array
    {
        $s = fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;

        return [
            'id' => $s($obj['id']),
            'pending' => $s($obj['pending']),
            'amount_cents' => $s($obj['amount_cents']),
            'success' => $s($obj['success']),
            'is_auth' => $s($obj['is_auth']),
            'is_capture' => $s($obj['is_capture']),
            'is_standalone_payment' => $s($obj['is_standalone_payment']),
            'is_voided' => $s($obj['is_voided']),
            'is_refunded' => $s($obj['is_refunded']),
            'is_3d_secure' => $s($obj['is_3d_secure']),
            'integration_id' => $s($obj['integration_id']),
            'has_parent_transaction' => $s($obj['has_parent_transaction']),
            'order' => $s($obj['order']['id']),
            'merchant_order_id' => $obj['order']['merchant_order_id'],
            'created_at' => $obj['created_at'],
            'currency' => $obj['currency'],
            'error_occured' => $s($obj['error_occured']),
            'owner' => $s($obj['owner']),
            'source_data.pan' => $obj['source_data']['pan'],
            'source_data.type' => $obj['source_data']['type'],
            'source_data.sub_type' => $obj['source_data']['sub_type'],
            'data.message' => $obj['data']['message'],
            'hmac' => $hmac ?? $this->hmacFor($obj),
        ];
    }

    private function heldStock(Order $order): float
    {
        return (float) StockMovement::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->sum('quantity');
    }

    /* ---------------------------------------------------------------------
     | Placing the order
     --------------------------------------------------------------------- */

    public function test_choosing_paymob_creates_a_pending_payment_order_and_redirects_to_paymob(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $governorate = Governorate::factory()->create(['shipping_fee' => 50]);

        $response = $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->form($governorate));

        $response->assertRedirect('https://accept.paymob.com/unifiedcheckout/?publicKey=egy_pk_test_123&clientSecret=egy_csk_test_secret');

        $order = Order::sole();
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(Order::PAYMENT_PAYMOB, $order->payment_method);
        $this->assertSame(Order::PAYMENT_UNPAID, $order->payment_status);
        $this->assertSame('pi_test_abc', $order->paymob_intention_id);
        $this->assertSame('987654', $order->paymob_order_id);

        // Stock is reserved straight away, like a COD order.
        $this->assertEqualsWithDelta(8.0, $product->fresh()->stock, 0.001);

        // The cart survives until Paymob confirms, so a failed payment can retry.
        $response->assertSessionHas('cart', [$product->id => 2]);
        $response->assertSessionHas('paymob_pending_order', $order->order_number);

        // Not a sale yet: no email to the shop.
        Mail::assertNothingSent();
    }

    public function test_the_intention_is_built_from_server_side_amounts_only(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product, 2);

        Http::assertSent(function (HttpRequest $request) use ($order) {
            $body = $request->data();
            $itemsTotal = array_sum(array_map(fn ($i) => $i['amount'] * $i['quantity'], $body['items']));

            return $request->url() === 'https://accept.paymob.com/v1/intention/'
                && $request->hasHeader('Authorization', 'Token egy_sk_test_456')
                && $body['amount'] === 35000            // (150 × 2 + 50) EGP in piastres
                && $itemsTotal === 35000                // items always add up to the amount
                && $body['currency'] === 'EGP'
                && $body['payment_methods'] === [111, 222]
                && $body['special_reference'] === $order->order_number
                && $body['billing_data']['phone_number'] === '+201012345678'
                && $body['notification_url'] === route('paymob.webhook')
                && $body['redirection_url'] === route('paymob.callback');
        });
    }

    public function test_paymob_is_refused_when_it_is_not_configured(): void
    {
        config(['services.paymob.secret_key' => null]);

        $product = Product::factory()->create(['stock' => 10]);
        $governorate = Governorate::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->form($governorate))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_the_checkout_only_offers_paymob_when_it_is_configured(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout'))
            ->assertSee('value="paymob"', false)
            ->assertSee('الدفع الإلكتروني عبر Paymob (بطاقات بنكية ومحافظ إلكترونية)');

        config(['services.paymob.hmac_secret' => null]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout'))
            ->assertDontSee('value="paymob"', false)
            ->assertSee('الدفع الإلكتروني غير متاح حالياً');
    }

    public function test_if_paymob_cannot_be_reached_the_order_fails_and_its_stock_is_released(): void
    {
        $this->paymobApi = fn () => Http::response(['detail' => 'Invalid integration'], 400);

        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);
        $governorate = Governorate::factory()->create();

        $this->withSession(['cart' => [$product->id => 2]])
            ->from(route('checkout'))
            ->post(route('checkout.store'), $this->form($governorate))
            ->assertRedirect(route('checkout'))
            ->assertSessionHas('cart_error');

        $order = Order::sole();
        $this->assertSame(Order::STATUS_FAILED_PAYMENT, $order->status);
        $this->assertSame(0.0, $this->heldStock($order));
        $this->assertEqualsWithDelta(5.0, $product->fresh()->stock, 0.001);
    }

    public function test_checking_out_again_releases_this_browsers_abandoned_paymob_order(): void
    {
        // Only 2 in stock and the first attempt reserved both. Without the
        // release, the customer's own reservation would block the COD retry.
        $product = Product::factory()->create(['stock' => 2]);
        $first = $this->placePaymobOrder($product, 2);

        $this->withSession(['cart' => [$product->id => 2], 'paymob_pending_order' => $first->order_number])
            ->post(route('checkout.store'), $this->form(Governorate::first(), ['payment_method' => 'cod']))
            ->assertRedirect();

        $this->assertSame(Order::STATUS_FAILED_PAYMENT, $first->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, Order::where('payment_method', 'cod')->sole()->status);
        $this->assertEqualsWithDelta(0.0, $product->fresh()->stock, 0.001);
    }

    /* ---------------------------------------------------------------------
     | Webhook (server to server)
     --------------------------------------------------------------------- */

    public function test_a_signed_successful_webhook_confirms_and_marks_the_order_paid(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->postWebhook($this->transaction($order))->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame('192837', $order->paymob_transaction_id);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->confirmed_at);

        // Stock stays taken — exactly once.
        $this->assertEqualsWithDelta(8.0, $product->fresh()->stock, 0.001);

        Mail::assertSent(AdminOrderPlacedNotification::class, fn ($mail) => $mail->hasTo('shop@nabta.test')
            && $mail->order->is($order));
    }

    public function test_a_repeated_webhook_is_idempotent(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);
        $obj = $this->transaction($order);

        $this->postWebhook($obj)->assertOk();
        $this->postWebhook($obj)->assertOk();

        $this->assertEqualsWithDelta(8.0, $product->fresh()->stock, 0.001);
        Mail::assertSent(AdminOrderPlacedNotification::class, 1);
    }

    public function test_a_webhook_with_a_bad_signature_is_rejected_and_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->postWebhook($this->transaction($order), str_repeat('0', 128))->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_webhook_whose_body_was_tampered_with_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        // Signed as a failure, then flipped to success in transit.
        $signedAsFailure = $this->transaction($order, ['success' => false]);
        $tampered = array_replace($signedAsFailure, ['success' => true]);

        $this->postWebhook($tampered, $this->hmacFor($signedAsFailure))->assertForbidden();

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_a_declined_webhook_fails_the_order_and_releases_its_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product, 3);

        $this->postWebhook($this->transaction($order, [
            'success' => false,
            'data' => ['message' => 'Insufficient funds'],
        ]))->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED_PAYMENT, $order->status);
        $this->assertSame(Order::PAYMENT_FAILED, $order->payment_status);
        $this->assertStringContainsString('Insufficient funds', $order->cancel_reason);
        $this->assertEqualsWithDelta(10.0, $product->fresh()->stock, 0.001);
        $this->assertTrue(StockMovement::where('type', StockMovementType::OnlineSaleReleased)->exists());

        Mail::assertNothingSent();
    }

    public function test_a_pending_wallet_transaction_leaves_the_order_waiting(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->postWebhook($this->transaction($order, ['pending' => true, 'success' => false]))->assertOk();

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertEqualsWithDelta(8.0, $product->fresh()->stock, 0.001);
    }

    public function test_a_signed_success_for_the_wrong_amount_is_not_marked_paid(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->postWebhook($this->transaction($order, ['amount_cents' => 100]))->assertOk();

        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_money_that_arrives_after_the_order_failed_still_confirms_it(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product, 2);

        $this->postWebhook($this->transaction($order, ['id' => 1, 'success' => false]))->assertOk();
        $this->assertEqualsWithDelta(10.0, $product->fresh()->stock, 0.001);

        // The customer retried on the same Paymob page and this time it went through.
        $this->postWebhook($this->transaction($order, ['id' => 2]))->assertOk();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNull($order->cancel_reason);
        $this->assertEqualsWithDelta(8.0, $product->fresh()->stock, 0.001);
    }

    public function test_a_valid_callback_cannot_be_redirected_to_another_customers_order(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $mine = $this->placePaymobOrder($product);

        // Someone else's pending order, with its own Paymob order id.
        $victim = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_method' => Order::PAYMENT_PAYMOB,
            'paymob_order_id' => '555000',
        ]);

        // merchant_order_id is not covered by the HMAC, so it can be edited
        // freely; the signature still verifies.
        $obj = $this->transaction($mine, ['success' => false]);
        $obj['order']['merchant_order_id'] = $victim->order_number;

        $this->postWebhook($obj)->assertOk();

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $victim->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $mine->fresh()->status);
    }

    public function test_a_malformed_hmac_parameter_is_rejected_not_a_server_error(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->postJson(route('paymob.webhook').'?hmac[]=x', ['type' => 'TRANSACTION', 'obj' => $this->transaction($order)])
            ->assertForbidden();

        $query = $this->redirectQuery($this->transaction($order));
        $query['amount_cents'] = ['1'];

        $this->get(route('paymob.callback', $query))->assertRedirect(route('shop'));
    }

    public function test_non_transaction_callbacks_are_acknowledged_and_ignored(): void
    {
        $this->postJson(route('paymob.webhook'), ['type' => 'TOKEN', 'obj' => ['token' => 'x']])
            ->assertOk();
    }

    /* ---------------------------------------------------------------------
     | Redirect (the customer's browser)
     --------------------------------------------------------------------- */

    public function test_a_successful_redirect_clears_the_cart_and_shows_the_confirmation(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'paymob_pending_order' => $order->order_number,
        ])->get(route('paymob.callback', $this->redirectQuery($this->transaction($order))));

        $response->assertRedirect(route('orders.confirmation', $order));
        $response->assertSessionMissing('cart');
        $response->assertSessionHas('recent_order', $order->order_number);

        $this->assertTrue($order->fresh()->isPaid());

        $this->get(route('orders.confirmation', $order))
            ->assertOk()
            ->assertSee('تم الدفع واستلام طلبك بنجاح')
            ->assertSee('الدفع الإلكتروني (Paymob)');
    }

    public function test_a_failed_redirect_returns_to_checkout_with_the_cart_intact(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'paymob_pending_order' => $order->order_number,
        ])->get(route('paymob.callback', $this->redirectQuery($this->transaction($order, ['success' => false]))));

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHas('cart_error');
        $response->assertSessionHas('cart', [$product->id => 2]);

        $this->assertSame(Order::STATUS_FAILED_PAYMENT, $order->fresh()->status);
        $this->assertEqualsWithDelta(10.0, $product->fresh()->stock, 0.001);
    }

    public function test_a_redirect_for_a_rejected_amount_does_not_pretend_all_is_well(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $response = $this->withSession([
            'cart' => [$product->id => 2],
            'paymob_pending_order' => $order->order_number,
        ])->get(route('paymob.callback', $this->redirectQuery($this->transaction($order, ['amount_cents' => 100]))));

        $response->assertRedirect(route('shop'));
        $response->assertSessionHas('cart', [$product->id => 2]);
        $response->assertSessionMissing('recent_order');
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_a_redirect_with_a_bad_signature_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->placePaymobOrder($product);

        $this->get(route('paymob.callback', $this->redirectQuery($this->transaction($order), 'forged')))
            ->assertRedirect(route('shop'));

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_a_replayed_redirect_from_another_browser_reveals_nothing(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $order = $this->placePaymobOrder($product);

        // No session tying this browser to the order.
        $this->flushSession();

        $this->get(route('paymob.callback', $this->redirectQuery($this->transaction($order))))
            ->assertRedirect(route('shop'))
            ->assertSessionMissing('recent_order');
    }

    public function test_the_admin_shows_online_payment_details(): void
    {
        $product = Product::factory()->create(['price' => 150, 'stock' => 10]);
        $paid = $this->placePaymobOrder($product);
        $this->postWebhook($this->transaction($paid))->assertOk();

        $this->actingAs(User::factory()->admin()->create());

        // Real rows, so every column closure actually runs.
        $this->get('/admin/orders')->assertSuccessful()->assertSee('مدفوع');

        // The link in the shop's email opens this order.
        $this->get(route('filament.admin.resources.orders.view', $paid))
            ->assertSuccessful()
            ->assertSee('192837')
            ->assertSee('الدفع الإلكتروني (Paymob)');
    }

    /* ---------------------------------------------------------------------
     | Expiry
     --------------------------------------------------------------------- */

    public function test_unpaid_orders_past_the_cut_off_are_failed_and_their_stock_released(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $stale = $this->placePaymobOrder($product, 2);
        $stale->forceFill(['placed_at' => now()->subMinutes(61)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(Order::STATUS_FAILED_PAYMENT, $stale->fresh()->status);
        $this->assertEqualsWithDelta(10.0, $product->fresh()->stock, 0.001);
    }

    public function test_the_expiry_sweep_does_not_skip_orders_across_pages(): void
    {
        // More stale orders than one page of the sweep (200). Failing each one
        // removes it from the query, which offset paging would trip over.
        Order::factory()->count(450)->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_method' => Order::PAYMENT_PAYMOB,
            'placed_at' => now()->subHours(3),
        ]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(0, Order::where('status', Order::STATUS_PENDING_PAYMENT)->count());
    }

    public function test_recent_unpaid_orders_are_left_alone(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $fresh = $this->placePaymobOrder($product, 2);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $fresh->fresh()->status);
    }

    /* ---------------------------------------------------------------------
     | Signature primitive
     --------------------------------------------------------------------- */

    public function test_signature_verification_rejects_a_missing_secret_or_hmac(): void
    {
        $obj = ['amount_cents' => 100];

        $this->assertFalse((new PaymobService(['hmac_secret' => '']))->verifyProcessedCallback($obj, 'abc'));
        $this->assertFalse((new PaymobService(['hmac_secret' => 'x']))->verifyProcessedCallback($obj, null));
        $this->assertFalse((new PaymobService(['hmac_secret' => 'x']))->verifyResponseCallback([]));
    }
}
