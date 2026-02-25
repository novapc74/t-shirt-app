<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductVariant|null $variant
 * @property-read Warehouse $warehouse
 * @method static StockFactory factory($count = null, $state = [])
 * @method static Builder<static>|Stock newModelQuery()
 * @method static Builder<static>|Stock newQuery()
 * @method static Builder<static>|Stock query()
 * @method static Builder<static>|Stock whereCreatedAt($value)
 * @method static Builder<static>|Stock whereId($value)
 * @method static Builder<static>|Stock whereProductVariantId($value)
 * @method static Builder<static>|Stock whereQuantity($value)
 * @method static Builder<static>|Stock whereUpdatedAt($value)
 * @method static Builder<static>|Stock whereWarehouseId($value)
 * @mixin Eloquent
 */
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
