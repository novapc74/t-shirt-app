<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
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
 * @property string $name
 * @property string $slug
 * @property int|null $measure_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Measure|null $measure
 * @property-read Collection<int, ProductVariantProperty> $variantValues
 * @property-read int|null $variant_values_count
 * @method static Builder<static>|Property newModelQuery()
 * @method static Builder<static>|Property newQuery()
 * @method static Builder<static>|Property query()
 * @method static Builder<static>|Property whereCreatedAt($value)
 * @method static Builder<static>|Property whereId($value)
 * @method static Builder<static>|Property whereMeasureId($value)
 * @method static Builder<static>|Property whereName($value)
 * @method static Builder<static>|Property whereSlug($value)
 * @method static Builder<static>|Property whereUpdatedAt($value)
 * @method static PropertyFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'measure_id'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantProperty::class);
    }
}
