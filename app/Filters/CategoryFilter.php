<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class CategoryFilter extends QueryFilter
{
    protected function apply(Builder $query): Builder
    {
        $category = request()->route('category');

//        return $query->where('category_id', $category->id);
        $categoryIds = $category->getAllSubcategoryIds();

        return $query->whereIn('category_id', $categoryIds);
    }

    protected function shouldFilter(): bool
    {
        return request()->route('category') !== null;
    }

    protected function filterKey(): string
    {
        return 'category';
    }
}

