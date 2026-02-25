<?php

namespace App\Models;

use Database\Factories\PriceTypeFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static PriceTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|PriceType newModelQuery()
 * @method static Builder<static>|PriceType newQuery()
 * @method static Builder<static>|PriceType query()
 * @method static Builder<static>|PriceType whereCreatedAt($value)
 * @method static Builder<static>|PriceType whereId($value)
 * @method static Builder<static>|PriceType whereName($value)
 * @method static Builder<static>|PriceType whereSlug($value)
 * @method static Builder<static>|PriceType whereUpdatedAt($value)
 * @property-read Collection<int, Price> $prices
 * @property-read int|null $prices_count
 * @mixin Eloquent
 */
class PriceType extends Model
{
    /** @use HasFactory<PriceTypeFactory> */
    use HasFactory;
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate(); // не менять URL, если сменили имя (полезно для SEO)
    }

    protected $fillable = [
        'name',
        'slug'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
