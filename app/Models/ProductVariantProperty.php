<?php

namespace App\Models;

use Database\Factories\ProductVariantPropertyFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $property_id
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Property $property
 * @property-read ProductVariant $variant
 * @method static Builder<static>|ProductVariantProperty newModelQuery()
 * @method static Builder<static>|ProductVariantProperty newQuery()
 * @method static Builder<static>|ProductVariantProperty query()
 * @method static Builder<static>|ProductVariantProperty whereCreatedAt($value)
 * @method static Builder<static>|ProductVariantProperty whereId($value)
 * @method static Builder<static>|ProductVariantProperty wherePropertyId($value)
 * @method static Builder<static>|ProductVariantProperty whereUpdatedAt($value)
 * @method static Builder<static>|ProductVariantProperty whereValue($value)
 * @method static Builder<static>|ProductVariantProperty whereVariantId($value)
 * @method static ProductVariantPropertyFactory factory($count = null, $state = [])
 * @mixin Eloquent
 */
class ProductVariantProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'property_id',
        'value'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
