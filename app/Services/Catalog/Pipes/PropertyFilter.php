<?php

namespace App\Services\Catalog\Pipes;

use App\Services\Catalog\DTO\ProductFilterDataDto;
use Closure;

class PropertyFilter
{
    public function handle(ProductFilterDataDto $data, Closure $next)
    {
        foreach ($data->filters as $slug => $values) {
            $values = array_filter((array)$values);
            if (empty($values)) continue;

            // Фильтруем товары, у которых есть варианты с нужным свойством
            $data->query->whereHas('variants.properties', function ($q) use ($slug, $values) {
                // ВАЖНО: Нам нужно прицепить саму таблицу свойств,
                // чтобы отфильтровать по slug
                $q->join('properties', 'product_variant_properties.property_id', '=', 'properties.id')
                    ->where('properties.slug', $slug)
                    ->whereIn('product_variant_properties.value', $values);
            });
        }

        return $next($data);
    }
}

