<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class StockFilter extends QueryFilter
{
    protected function apply(Builder $query): Builder
    {
        return $query->whereHas('variants.stocks', function ($sQuery) {
            $sQuery->where('quantity', '>', 0);
        });
    }

    protected function shouldFilter(): bool { return true; } // Всегда фильтруем наличие
    protected function filterKey(): string { return 'stock'; }
}

