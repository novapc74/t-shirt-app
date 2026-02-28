<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Category|null $parent
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @method static CategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|Category newModelQuery()
 * @method static Builder<static>|Category find($value)
 * @method static Builder<static>|Category newQuery()
 * @method static Builder<static>|Category query()
 * @method static Builder<static>|Category whereCreatedAt($value)
 * @method static Builder<static>|Category whereId($value)
 * @method static Builder<static>|Category whereName($value)
 * @method static Builder<static>|Category whereParentId($value)
 * @method static Builder<static>|Category whereSlug($value)
 * @method static Builder<static>|Category whereUpdatedAt($value)
 * @property string $title
 * @property int $priority
 * @property-read Collection<int, Category> $childrenRecursive
 * @property-read int|null $children_recursive_count
 * @property-read mixed $total_variants_count
 * @property-read Collection<int, \App\Models\ProductVariant> $variants
 * @property-read int|null $variants_count
 * @method static Builder<static>|Category wherePriority($value)
 * @method static Builder<static>|Category whereTitle($value)
 * @mixin Eloquent
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'parent_id',
        'title',
        'slug',
        'priority',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Настройка автоматической генерации слага
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    protected static function booted(): void
    {
        static::saving(function ($category) {
            if ($category->parent_id) {
                $parent = self::find($category->parent_id);

                if ($parent && $parent->products()->exists()) {
                    throw new Exception("Cannot add subcategory to a category that has products.");
                }
            }
        });
    }

    /**
     * Использовать 'slug' вместо 'id' в URL (Route Model Binding)
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Связь с родителем
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Связь с подкатегориями
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('priority');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Связь с товарами
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariant::class, Product::class);
    }

    protected $appends = ['total_variants_count'];

    public function getTotalVariantsCountAttribute()
    {
        // Считаем свои варианты + варианты всех вложенных детей
        $count = $this->variants_count ?? 0;

        foreach ($this->childrenRecursive as $child) {
            $count += $child->total_variants_count;
        }

        return $count;
    }

    public function getAllSubcategoryIds(): array
    {
        $ids = [$this->id];

        foreach ($this->childrenRecursive as $child) {
            $ids = array_merge($ids, $child->getAllSubcategoryIds());
        }

        return $ids;
    }
}
