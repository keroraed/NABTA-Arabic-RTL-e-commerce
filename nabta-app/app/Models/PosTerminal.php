<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'warehouse_id', 'cash_account_id', 'is_active', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    public function openShift(): ?CashShift
    {
        return $this->shifts()->where('status', CashShift::OPEN)->latest('opened_at')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
