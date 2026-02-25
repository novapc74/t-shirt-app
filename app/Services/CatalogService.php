<?php

namespace App\Services;

use App\Models\{Category, Product, Property};
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Database\Eloquent\Builder;

class CatalogService
{
    public function getCategoryData(Category $category): ?array
    {
        $filters = (array)request('filters', []);
        $sort = request('sort', 'newest');

        return [];
    }
}
