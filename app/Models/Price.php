<?php

namespace App\Models;

use Database\Factories\PriceFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property int $price_type_id
 * @property numeric $amount
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceType $type
 * @property-read ProductVariant $variant
 * @method static PriceFactory factory($count = null, $state = [])
 * @method static Builder<static>|Price newModelQuery()
 * @method static Builder<static>|Price newQuery()
 * @method static Builder<static>|Price query()
 * @method static Builder<static>|Price whereAmount($value)
 * @method static Builder<static>|Price whereCreatedAt($value)
 * @method static Builder<static>|Price whereCurrency($value)
 * @method static Builder<static>|Price whereId($value)
 * @method static Builder<static>|Price wherePriceTypeId($value)
 * @method static Builder<static>|Price whereProductVariantId($value)
 * @method static Builder<static>|Price whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'price_type_id',
        'amount',
        'currency'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PriceType::class, 'price_type_id');
    }
}
