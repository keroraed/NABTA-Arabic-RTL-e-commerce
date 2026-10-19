<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED = 'returned';

    /**
     * pending -> confirmed -> shipped -> delivered
     *    |           |           |
     * cancelled  cancelled   returned
     *
     * pending -> confirmed is the confirmation phone call. It is the main lever
     * against fake COD orders, so it is a real transition, not a formality.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_RETURNED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_RETURNED => [],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'قيد المراجعة',
        self::STATUS_CONFIRMED => 'تم التأكيد',
        self::STATUS_SHIPPED => 'تم الشحن',
        self::STATUS_DELIVERED => 'تم التسليم',
        self::STATUS_CANCELLED => 'ملغي',
        self::STATUS_RETURNED => 'مرتجع',
    ];

    protected $fillable = [
        'order_number', 'status', 'payment_method', 'channel',
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

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
