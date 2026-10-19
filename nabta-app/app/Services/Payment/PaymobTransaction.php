<?php

namespace App\Services\Payment;

/**
 * One Paymob transaction result, normalised from either of the two shapes
 * Paymob sends it in: the server-to-server "processed" webhook (JSON, nested
 * under `obj`) and the browser "response" redirect (flat query string).
 *
 * Only build one of these from a payload whose HMAC has already been verified.
 */
final readonly class PaymobTransaction
{
    public function __construct(
        public string $id,
        public bool $success,
        public bool $pending,
        public bool $voided,
        public bool $refunded,
        public int $amountCents,
        public string $currency,
        public ?string $merchantOrderId,
        public ?string $paymobOrderId,
        public ?string $message = null,
    ) {}

    /** @param  array<string, mixed>  $obj  the `obj` member of the webhook body */
    public static function fromProcessedCallback(array $obj): self
    {
        return new self(
            id: (string) data_get($obj, 'id', ''),
            success: self::bool(data_get($obj, 'success')),
            pending: self::bool(data_get($obj, 'pending')),
            voided: self::bool(data_get($obj, 'is_voided')),
            refunded: self::bool(data_get($obj, 'is_refunded')),
            amountCents: (int) data_get($obj, 'amount_cents', 0),
            currency: (string) data_get($obj, 'currency', ''),
            merchantOrderId: self::stringOrNull(data_get($obj, 'order.merchant_order_id')),
            paymobOrderId: self::stringOrNull(data_get($obj, 'order.id')),
            message: self::stringOrNull(data_get($obj, 'data.message')),
        );
    }

    /**
     * PHP turns the dots in Paymob's query keys into underscores
     * ("data.message" arrives as "data_message").
     *
     * @param  array<string, mixed>  $query
     */
    public static function fromResponseQuery(array $query): self
    {
        return new self(
            id: (string) ($query['id'] ?? ''),
            success: self::bool($query['success'] ?? null),
            pending: self::bool($query['pending'] ?? null),
            voided: self::bool($query['is_voided'] ?? null),
            refunded: self::bool($query['is_refunded'] ?? null),
            amountCents: (int) ($query['amount_cents'] ?? 0),
            currency: (string) ($query['currency'] ?? ''),
            merchantOrderId: self::stringOrNull($query['merchant_order_id'] ?? null),
            paymobOrderId: self::stringOrNull($query['order'] ?? $query['order_id'] ?? null),
            message: self::stringOrNull($query['data_message'] ?? null),
        );
    }

    /** Money actually moved and has not been reversed. */
    public function isSuccessful(): bool
    {
        return $this->success && ! $this->pending && ! $this->voided && ! $this->refunded;
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
