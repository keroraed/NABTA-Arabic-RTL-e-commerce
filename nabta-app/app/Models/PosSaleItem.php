<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_sale_id', 'product_id', 'original_item_id', 'product_name_snapshot', 'unit_price_snapshot',
        'cost_at_sale', 'quantity', 'weight_kg', 'discount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'cost_at_sale' => 'decimal:2',
            'quantity' => 'decimal:3',
            'weight_kg' => 'decimal:3',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isWeighted(): bool
    {
        return $this->weight_kg !== null;
    }

    /** Stock leaves in kg for weighted goods, in units otherwise. */
    public function stockQuantity(): float
    {
        return $this->isWeighted() ? (float) $this->weight_kg : (float) $this->quantity;
    }
}
