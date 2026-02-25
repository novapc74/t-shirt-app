<?php

namespace App\Models;

use Eloquent;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Price> $prices
 * @property-read int|null $prices_count
 * @property-read Product $product
 * @property-read Collection<int, Stock> $stocks
 * @property-read int|null $stocks_count
 * @method static ProductVariantFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereSku($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 * @method static Builder<static>|ProductVariant whereAttribute(string $string, mixed $string1)
 * @property-read Collection<int, ProductVariantProperty> $variantProperties
 * @property-read int|null $variant_properties_count
 * @mixin Eloquent
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(ProductVariantProperty::class, 'variant_id');
    }
}
