<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'type', 'is_active', 'is_online_fulfilment'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_online_fulfilment' => 'boolean'];
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The warehouse the storefront sells from. Falls back to the first one so a
     * misconfigured install still takes orders rather than throwing at checkout.
     */
    public static function onlineFulfilment(): ?self
    {
        return static::query()->where('is_online_fulfilment', true)->first()
            ?? static::query()->orderBy('id')->first();
    }
}
