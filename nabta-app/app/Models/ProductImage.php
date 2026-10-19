<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id', 'path', 'alt_ar', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Paths are stored relative to the "assets" disk — that is, public/assets —
     * so a seeded image and one uploaded through the admin resolve identically
     * and no storage:link is involved on the shared-hosting target.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('assets')->url($this->path);
    }
}
