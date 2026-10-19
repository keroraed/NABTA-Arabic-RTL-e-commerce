<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes.
 *
 * Records an append-only movement and updates the cached stock_levels row in the
 * same statement. The cache is updated with an atomic in-database increment, not
 * a read-modify-write, so two terminals syncing at once cannot lose an update.
 *
 * Negative balances are allowed by design: the customer is at the counter
 * holding the item, so a sale is never blocked on stock. A negative balance
 * self-corrects on the next purchase receipt, and the admin has a report for it.
 */
class StockLedger
{
    public function record(
        Product|int $product,
        Warehouse|int $warehouse,
        StockMovementType $type,
        float $quantity,
        ?Model $reference = null,
        ?float $unitCost = null,
        ?string $notes = null,
        ?\DateTimeInterface $occurredAt = null,
        ?int $userId = null,
    ): StockMovement {
        $productId = $product instanceof Product ? $product->id : $product;
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        // The caller passes a magnitude; the type decides the sign. This keeps
        // every call site from having to remember which way a movement runs.
        $signed = $type->isInbound() || $type === StockMovementType::Adjustment
            ? $quantity
            : -abs($quantity);

        return DB::transaction(function () use (
            $productId, $warehouseId, $type, $signed, $reference, $unitCost, $notes, $occurredAt, $userId
        ) {
            $movement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $signed,
                'unit_cost' => $unitCost,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => $userId ?? auth()->id(),
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $this->applyToCache($productId, $warehouseId, $signed);

            return $movement;
        });
    }

    /**
     * Atomic upsert-then-increment. Doing this as SELECT-then-UPDATE would drop
     * writes whenever two sales for the same product land concurrently.
     */
    private function applyToCache(int $productId, int $warehouseId, float $delta): void
    {
        StockLevel::query()->firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0],
        );

        StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->update(['quantity' => DB::raw('quantity + '.$this->sql($delta))]);
    }

    /** Formats a float for inline SQL without locale surprises. */
    private function sql(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    public function balance(Product|int $product, Warehouse|int|null $warehouse = null): float
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $query = StockLevel::query()->where('product_id', $productId);

        if ($warehouse !== null) {
            $query->where('warehouse_id', $warehouse instanceof Warehouse ? $warehouse->id : $warehouse);
        }

        return round((float) $query->sum('quantity'), 3);
    }

    /**
     * Replays the ledger to regenerate stock_levels. The rebuild is the
     * arbiter: if it disagrees with the cache, the cache was wrong.
     */
    public function rebuildCache(): int
    {
        return DB::transaction(function () {
            StockLevel::query()->delete();

            $rows = StockMovement::query()
                ->selectRaw('product_id, warehouse_id, SUM(quantity) as total')
                ->groupBy('product_id', 'warehouse_id')
                ->get();

            foreach ($rows as $row) {
                StockLevel::create([
                    'product_id' => $row->product_id,
                    'warehouse_id' => $row->warehouse_id,
                    'quantity' => $row->total,
                ]);
            }

            return $rows->count();
        });
    }
}
