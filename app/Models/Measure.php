<?php

namespace App\Models;

use Database\Factories\MeasureFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Property> $properties
 * @property-read int|null $properties_count
 * @method static Builder<static>|Measure newModelQuery()
 * @method static Builder<static>|Measure newQuery()
 * @method static Builder<static>|Measure query()
 * @method static Builder<static>|Measure whereCreatedAt($value)
 * @method static Builder<static>|Measure whereId($value)
 * @method static Builder<static>|Measure whereName($value)
 * @method static Builder<static>|Measure whereSymbol($value)
 * @method static Builder<static>|Measure whereUpdatedAt($value)
 * @method static MeasureFactory factory($count = null, $state = [])
 * @mixin Eloquent
 */
class Measure extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol'
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
