<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suppliers and customers. Both carry a running balance derived from the same
 * append-only ledger — see App\Services\Accounting\PartyLedger for the
 * debit/credit convention.
 */
class Party extends Model
{
    use HasFactory;

    public const TYPE_SUPPLIER = 'supplier';
    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_LABELS = [
        self::TYPE_SUPPLIER => 'مورد',
        self::TYPE_CUSTOMER => 'عميل',
    ];

    protected $fillable = ['type', 'name', 'phone', 'notes', 'opening_balance', 'is_active'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeCustomers($query)
    {
        return $query->where('type', self::TYPE_CUSTOMER);
    }

    public function scopeSuppliers($query)
    {
        return $query->where('type', self::TYPE_SUPPLIER);
    }

    public function isSupplier(): bool
    {
        return $this->type === self::TYPE_SUPPLIER;
    }

    /**
     * Current balance, derived — never stored.
     *
     * Supplier: SUM(credit) - SUM(debit) is what we still owe them.
     * Customer: SUM(debit) - SUM(credit) is what they still owe us.
     * Positive always means "money is owed", in whichever direction this party
     * implies, so the admin can render one column without branching.
     */
    public function balance(): float
    {
        $sums = $this->ledgerEntries()
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $debit = (float) ($sums->d ?? 0);
        $credit = (float) ($sums->c ?? 0);

        return $this->isSupplier()
            ? round($credit - $debit, 2)
            : round($debit - $credit, 2);
    }
}
