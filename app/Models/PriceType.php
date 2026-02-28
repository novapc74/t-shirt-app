<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Price> $prices
 * @property-read int|null $prices_count
 * @method static \Database\Factories\PriceTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PriceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'priority',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
