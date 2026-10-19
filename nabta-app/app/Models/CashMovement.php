<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/** Append-only. Write through App\Services\Accounting\Treasury. */
class CashMovement extends Model
{
    use HasFactory;

    public const IN = 'in';
    public const OUT = 'out';

    protected $fillable = [
        'cash_account_id', 'direction', 'amount', 'reference_type',
        'reference_id', 'description', 'occurred_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('cash_movements is append-only; post a reversing movement instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('cash_movements is append-only; post a reversing movement instead.');
        });
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
