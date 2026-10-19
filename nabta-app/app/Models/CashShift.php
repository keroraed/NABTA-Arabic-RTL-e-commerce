<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use HasFactory;

    public const OPEN = 'open';
    public const CLOSED = 'closed';

    protected $fillable = [
        'pos_terminal_id', 'opened_by', 'closed_by', 'opened_at', 'opening_float',
        'closed_at', 'counted_cash', 'expected_cash', 'variance', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * What should be in the drawer: the opening float plus the cash actually
     * taken during the shift. Credit portions of a sale are deliberately
     * excluded, because no money changed hands for those.
     */
    public function expectedCash(): float
    {
        return round(
            (float) $this->opening_float + (float) $this->sales()->sum('paid_cash'),
            2,
        );
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
