<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/** Append-only. Write through App\Services\Accounting\PartyLedger. */
class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id', 'entry_date', 'description', 'debit', 'credit',
        'reference_type', 'reference_id', 'cash_account_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('ledger_entries is append-only; post a reversing entry instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('ledger_entries is append-only; post a reversing entry instead.');
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
