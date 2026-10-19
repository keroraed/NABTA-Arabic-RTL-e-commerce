<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A category, or a subcategory when it has a parent. Two levels only —
 * category › subcategory — which is what the shop's filter rows present.
 *
 * Products are filed through the category_product pivot, so a product can sit
 * in several categories at once. `products.category_id` remains the product's
 * primary category and is always also in the pivot — see Product::booted().
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'slug', 'name_ar', 'title_ar', 'badge_ar', 'description_ar', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Every product filed under this category (primary or additional). */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    /** Products whose PRIMARY category this is. */
    public function primaryProducts(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The subcategories directly below this one. (Named children() rather than
     * subcategories() so it does not collide with scopeSubcategories().)
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Top-level categories only. */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Categories that have a parent. */
    public function scopeSubcategories(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /** "الأعشاب › أعشاب للشاي" — for admin selects where the bare name is ambiguous. */
    public function getFullNameAttribute(): string
    {
        return $this->parent ? $this->parent->name_ar.' › '.$this->name_ar : $this->name_ar;
    }

    /**
     * Ids of every category below this one, at any depth. Walks one level per
     * query; category trees here are two or three levels, not hundreds.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::query()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();

            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /**
     * This category plus everything below it — what a shopper means by
     * "show me this category".
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        return [$this->id, ...$this->descendantIds()];
    }

    protected static function booted(): void
    {
        // The admin form only offers valid parents; this is the backstop for
        // every other write path. It also rules out cycles, which would make
        // descendantIds() — and so every category filter — never terminate.
        static::saving(function (self $category) {
            if ($category->parent_id === null || ! $category->isDirty('parent_id')) {
                return;
            }

            if ($category->exists && (int) $category->parent_id === $category->id) {
                throw new InvalidArgumentException('لا يمكن أن يكون القسم تابعاً لنفسه.');
            }

            if (static::query()->whereKey($category->parent_id)->whereNotNull('parent_id')->exists()) {
                throw new InvalidArgumentException('القسم الرئيسي المختار هو نفسه قسم فرعي. الأقسام مستويان فقط.');
            }

            if ($category->exists && $category->children()->exists()) {
                throw new InvalidArgumentException('هذا القسم له أقسام فرعية، فلا يمكن جعله قسماً فرعياً.');
            }
        });
    }
}
