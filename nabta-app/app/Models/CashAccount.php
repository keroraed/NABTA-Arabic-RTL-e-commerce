<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'opening_balance', 'is_active'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Derived: opening + in - out. The treasury report asserts this reconciles.
     */
    public function balance(): float
    {
        $sums = $this->movements()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as inflow")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outflow")
            ->first();

        return round(
            (float) $this->opening_balance + (float) ($sums->inflow ?? 0) - (float) ($sums->outflow ?? 0),
            2,
        );
    }
}
