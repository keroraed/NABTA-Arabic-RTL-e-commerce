<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'legacy_id', 'sku', 'category_id',
        'name_ar', 'short_desc_ar', 'description_ar', 'ingredients_ar', 'usage_ar',
        'weight_label', 'price', 'compare_at_price', 'currency',
        'is_active', 'is_featured', 'sort_order',
        'is_weighted', 'plu_code', 'price_per_kg', 'barcode', 'sync_to_scale', 'average_cost',
        // Not a column — see setStockAttribute(). Fillable so fill()/update()
        // route it through the mutator rather than silently dropping it.
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_weighted' => 'boolean',
            'sync_to_scale' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /* ---------------------------------------------------------------------
     | Stock
     |
     | There is no products.stock column any more. Stock is derived from the
     | append-only ledger in stock_movements, cached per warehouse in
     | stock_levels. `$product->stock` is kept as a read-only accessor so the
     | storefront reads naturally, but it now means "on hand in the warehouse
     | the storefront sells from" rather than one global number.
     |
     | Anything that CHANGES stock must go through StockLedger.
     --------------------------------------------------------------------- */

    /**
     * Attaches the online-fulfilment balance as `online_stock` in one subquery,
     * so a product listing does not fire a query per row.
     */
    public function scopeWithOnlineStock(Builder $query): Builder
    {
        $warehouseId = Warehouse::onlineFulfilment()?->id;

        return $query->addSelect([
            'online_stock' => StockLevel::query()
                ->selectRaw('COALESCE(quantity, 0)')
                ->whereColumn('product_id', 'products.id')
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->limit(1),
        ]);
    }

    /**
     * Writing to `stock` SETS the counted balance in the online-fulfilment
     * warehouse, posting an `adjustment` movement for the difference. That is
     * exactly what a physical stock count is, so seeders, CSV imports and the
     * admin can all express "there are 24 of these" naturally without anyone
     * writing to the ledger by hand.
     *
     * It is a shim, not a column: the value is stashed here and materialised in
     * the `saved` hook, once the row is guaranteed to have an id.
     */
    public function setStockAttribute(float|int|string|null $value): void
    {
        $this->pendingStockCount = $value === null ? null : (float) $value;
    }

    protected ?float $pendingStockCount = null;

    protected static function booted(): void
    {
        static::saved(function (self $product) {
            if ($product->pendingStockCount === null) {
                return;
            }

            $target = $product->pendingStockCount;
            $product->pendingStockCount = null;

            $warehouse = Warehouse::onlineFulfilment();

            if (! $warehouse) {
                return;
            }

            $delta = round($target - $product->stockAt($warehouse), 3);

            if (abs($delta) < 0.0005) {
                return;
            }

            app(StockLedger::class)->record(
                product: $product,
                warehouse: $warehouse,
                type: StockMovementType::Adjustment,
                quantity: $delta,
                notes: 'ضبط الرصيد',
            );
        });
    }

    public function getStockAttribute(): float
    {
        // Set by scopeWithOnlineStock() when the caller opted into the subquery.
        if (array_key_exists('online_stock', $this->attributes)) {
            return (float) ($this->attributes['online_stock'] ?? 0);
        }

        return $this->stockAt(Warehouse::onlineFulfilment()?->id);
    }

    public function stockAt(Warehouse|int|null $warehouse): float
    {
        if ($warehouse === null) {
            return 0.0;
        }

        $id = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        if ($this->relationLoaded('stockLevels')) {
            return (float) $this->stockLevels->firstWhere('warehouse_id', $id)?->quantity ?? 0.0;
        }

        return (float) ($this->stockLevels()->where('warehouse_id', $id)->value('quantity') ?? 0);
    }

    /** Total on hand across every warehouse. */
    public function totalStock(): float
    {
        return round((float) $this->stockLevels()->sum('quantity'), 3);
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= 5;
    }

    /* --------------------------------------------------------------------- */

    /** Weighted goods are priced per kilo; everything else uses the shelf price. */
    public function effectiveUnitPrice(): float
    {
        return $this->is_weighted
            ? (float) ($this->price_per_kg ?? 0)
            : (float) $this->price;
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->images->first()?->url;
    }

    /**
     * The raw disk-relative path of the first image ("products/x.webp"), for
     * Filament\Tables\Columns\ImageColumn::disk('assets'). That component
     * resolves its own URL from a disk + relative path; feeding it our already-
     * resolved, host-relative URL from primary_image_url instead makes it check
     * existence against the wrong disk (Filament's default is 'public') and
     * silently render nothing for every row.
     */
    public function getPrimaryImagePathAttribute(): ?string
    {
        return $this->images->first()?->path;
    }
}
