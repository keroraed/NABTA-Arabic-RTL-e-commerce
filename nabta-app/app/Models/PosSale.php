<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use HasFactory;

    public const CASH = 'cash';
    public const CREDIT = 'credit';
    public const PARTIAL = 'partial';

    public const PAYMENT_LABELS = [
        self::CASH => 'نقدي',
        self::CREDIT => 'آجل',
        self::PARTIAL => 'دفع جزئي',
    ];

    protected $fillable = [
        'uuid', 'pos_terminal_id', 'cash_shift_id', 'receipt_number', 'type',
        'original_sale_id', 'party_id', 'subtotal', 'discount', 'tax_amount',
        'total', 'payment_method', 'paid_cash', 'paid_on_credit', 'cash_received',
        'change_given', 'sold_by', 'sold_at', 'clock_offset_ms', 'received_at',
        'synced_at', 'has_total_discrepancy',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_cash' => 'decimal:2',
            'paid_on_credit' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'sold_at' => 'datetime',
            'received_at' => 'datetime',
            'synced_at' => 'datetime',
            'has_total_discrepancy' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_LABELS[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * How long the sale took to reach the server. A large gap means the terminal
     * was offline, which the admin surfaces to spot connectivity problems.
     */
    public function syncLagMinutes(): ?int
    {
        if (! $this->received_at) {
            return null;
        }

        return (int) $this->sold_at->diffInMinutes($this->received_at);
    }
}
