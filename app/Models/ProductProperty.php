<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $property_value_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty wherePropertyValueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductProperty whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProductProperty extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'property_value_id',
    ];
}
