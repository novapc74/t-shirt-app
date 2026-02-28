<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PropertyValue> $propertyValues
 * @property-read int|null $property_values_count
 * @method static \Database\Factories\MeasureFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Measure whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Measure extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'title',
        'slug',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    /**
     * Получить все значения характеристик, использующие эту единицу измерения.
     * Например: $kgMeasure->propertyValues;
     */
    public function propertyValues(): HasMany
    {
        return $this->hasMany(PropertyValue::class, 'measure_id');
    }
}
