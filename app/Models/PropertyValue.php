<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $property_id
 * @property int|null $measure_id
 * @property string $value
 * @property string $slug
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Measure|null $measure
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereMeasureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyValue whereValue($value)
 * @mixin \Eloquent
 */
class PropertyValue extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'property_id',
        'measure_id',
        'value',
        'slug',
        'priority',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('value')
            ->saveSlugsTo('slug')
            // ГЛАВНОЕ: Указываем, что уникальность проверяется только внутри property_id
            ->extraScope(fn($builder) => $builder->where('property_id', $this->property_id))
            ->doNotGenerateSlugsOnUpdate(); // Обычно слаги лучше не менять при обновлении для SEO
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
