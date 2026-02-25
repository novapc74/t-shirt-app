<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class PriceFilter extends QueryFilter
{
    /**
     * Ключ в массиве filters, по которому определяем активность
     */
    protected function filterKey(): string
    {
        return 'filters.min_price'; // или 'filters.max_price'
    }

    /**
     * Переопределяем проверку, так как у нас два ключа (min и max)
     */
    protected function shouldFilter(): bool
    {
        return request()->filled('filters.min_price') || request()->filled('filters.max_price');
    }

    protected function apply(Builder $query): Builder
    {
        $min = request('filters.min_price');
        $max = request('filters.max_price');

        return $query->whereHas('variants.prices', function ($q) use ($min, $max) {
            $q->where('price_type_id', 1); // Розничная цена

            if ($min) {
                $q->where('amount', '>=', (float) $min);
            }

            if ($max) {
                $q->where('amount', '<=', (float) $max);
            }
        });
    }
}
