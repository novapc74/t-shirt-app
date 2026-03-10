<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $color_id
 * @property int|null $size_id
 * @property int|null $gender_id
 * @property string $sku
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Color|null $color
 * @property-read Gender|null $gender
 * @property-read Collection<int, Price> $prices
 * @property-read int|null $prices_count
 * @property-read Product $product
 * @property-read Size|null $size
 * @property-read Collection<int, Stock> $stocks
 * @property-read int|null $stocks_count
 * @method static ProductVariantFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereColorId($value)
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereGenderId($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereIsDefault($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereSizeId($value)
 * @method static Builder<static>|ProductVariant whereSku($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'color_id',
        'size_id',
        'gender_id',
        'sku',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
