<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED = 'returned';

    /** Paymob orders only: placed, stock reserved, money not yet received. */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    /** Paymob orders only: declined, abandoned or expired. Stock released. */
    public const STATUS_FAILED_PAYMENT = 'failed_payment';

    public const PAYMENT_COD = 'cod';
    public const PAYMENT_PAYMOB = 'paymob';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_COD => 'الدفع عند الاستلام',
        self::PAYMENT_PAYMOB => 'الدفع الإلكتروني (Paymob)',
    ];

    public const PAYMENT_STATUS_LABELS = [
        self::PAYMENT_UNPAID => 'لم يُدفع بعد',
        self::PAYMENT_PAID => 'مدفوع',
        self::PAYMENT_FAILED => 'فشل الدفع',
    ];

    /**
     * pending -> confirmed -> shipped -> delivered
     *    |           |           |
     * cancelled  cancelled   returned
     *
     * pending -> confirmed is the confirmation phone call. It is the main lever
     * against fake COD orders, so it is a real transition, not a formality.
     *
     * pending_payment -> confirmed | failed_payment is driven ONLY by Paymob
     * (see App\Services\Payment\OrderPaymentSettler), never by staff: an order
     * must not be marked paid by hand, so neither state offers a staff action.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_RETURNED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_RETURNED => [],
        self::STATUS_PENDING_PAYMENT => [],
        self::STATUS_FAILED_PAYMENT => [],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'قيد المراجعة',
        self::STATUS_CONFIRMED => 'تم التأكيد',
        self::STATUS_SHIPPED => 'تم الشحن',
        self::STATUS_DELIVERED => 'تم التسليم',
        self::STATUS_CANCELLED => 'ملغي',
        self::STATUS_RETURNED => 'مرتجع',
        self::STATUS_PENDING_PAYMENT => 'في انتظار الدفع',
        self::STATUS_FAILED_PAYMENT => 'فشل الدفع',
    ];

    protected $fillable = [
        'order_number', 'status', 'payment_method', 'payment_status', 'channel',
        'paymob_intention_id', 'paymob_order_id', 'paymob_transaction_id', 'paid_at', 'admin_notified_at',
        'customer_name', 'customer_phone', 'customer_alt_phone',
        'governorate_id', 'governorate_name_snapshot', 'city', 'address_line', 'landmark', 'notes',
        'subtotal', 'shipping_fee', 'total', 'currency', 'ip_address',
        'placed_at', 'confirmed_at', 'shipped_at', 'delivered_at',
        'cancelled_at', 'returned_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'admin_notified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPaymob(): bool
    {
        return $this->payment_method === self::PAYMENT_PAYMOB;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? $this->payment_method;
    }

    /** COD is never "unpaid" in a worrying sense — it is collected at the door. */
    public function paymentStatusLabel(): string
    {
        if ($this->payment_method === self::PAYMENT_COD && $this->payment_status === self::PAYMENT_UNPAID) {
            return 'يُحصَّل عند الاستلام';
        }

        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }

    /** Integer piastres, which is what Paymob deals in. */
    public function totalInPiastres(): int
    {
        return (int) round((float) $this->total * 100);
    }

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
