<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only. Writes go through App\Services\Inventory\StockLedger, never
 * directly, so the cached stock_levels row is always updated in the same
 * transaction. Updating or deleting a movement is blocked outright: a
 * correction is another movement in the opposite direction.
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'warehouse_id', 'type', 'quantity', 'unit_cost',
        'reference_type', 'reference_id', 'notes', 'created_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('stock_movements is append-only; record a reversing movement instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('stock_movements is append-only; record a reversing movement instead.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
