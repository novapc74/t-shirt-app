<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class AttributeFilter extends QueryFilter
{
    protected function apply(Builder $query): Builder
    {
        $filters = request('filters', []);

        foreach ($filters as $slug => $values) {
            $values = (array) $values;
            if (empty($values)) continue;

            $query->whereHas('variants', function ($vQuery) use ($slug, $values) {
                $vQuery->whereHas('properties', function ($propQuery) use ($slug, $values) {
                    $propQuery->whereHas('property', function ($p) use ($slug) {
                        $p->where('slug', $slug);
                    })->whereIn('value', $values);
                });
            });
        }

        return $query;
    }

    protected function filterKey(): string { return 'filters'; }
}

