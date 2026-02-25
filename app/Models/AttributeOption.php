<?php

namespace App\Models;

use Database\Factories\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static updateOrCreate(string[] $array, string[] $array1)
 * @method static AttributeOptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|AttributeOption newModelQuery()
 * @method static Builder<static>|AttributeOption newQuery()
 * @method static Builder<static>|AttributeOption query()
 * @method static Builder<static>|AttributeOption type(string $type)
 * @mixin \Eloquent
 */
class AttributeOption extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'value',
        'label'
    ];

    /**
     * Быстрый поиск по типу (например: AttributeOption::type('color')->get())
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
