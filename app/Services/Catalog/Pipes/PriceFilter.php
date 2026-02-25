<?php

namespace App\Services\Catalog\Pipes;

use App\Models\PriceType;
use App\Services\Catalog\DTO\ProductFilterDataDto;
use Closure;

class PriceFilter implements CatalogPipelineInterface
{

    public function handle(ProductFilterDataDto $data, Closure $next): ProductFilterDataDto
    {
        if (!$data->minPrice && !$data->maxPrice) {
            return $next($data);
        }

        $data->query->whereHas('variants', function ($q) use ($data) {
            $q->join('prices', 'product_variants.id', '=', 'prices.product_variant_id')
                ->where('prices.price_type_id', PriceType::RETAIL);

            if ($data->minPrice) $q->where('prices.amount', '>=', $data->minPrice);
            if ($data->maxPrice) $q->where('prices.amount', '<=', $data->maxPrice);
        });

        return $next($data);
    }
}
