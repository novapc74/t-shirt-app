<?php

namespace App\Services\Catalog;

use App\Models\{Product, Category};
use App\Services\Catalog\DTO\ProductFilterParams;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class CatalogService
{
    public function __construct(private FilterService $filterService)
    {
    }

    public function getCategoryCatalog(Category $category, ProductFilterParams $params): ?array
    {
        // 1. Получаем ID товаров, подходящих под фильтры (через быстрый индекс)
        $productIds = $this->filterService->getFilteredProductIds($params, $category->id);

        if (empty($productIds)) {
            return null;
        }

        // 2. Загружаем товары для текущей страницы с необходимыми связями
        /** @var LengthAwarePaginator $products */
        $products = Product::query()
            ->with(['brand', 'variants.color', 'variants.size', 'variants.prices'])
            ->whereIn('id', $productIds)
            // Здесь можно добавить логику сортировки из $params->sort
            ->when($params->sort === 'newest', fn($q) => $q->latest())
            ->when($params->sort === 'price_asc', fn($q) => $q->orderBy(
                \App\Models\Price::select('price')
                    ->whereColumn('product_variant_id', 'product_variants.id')
                    ->limit(1)
            ))
            ->paginate(20)
            ->withQueryString();

        return [
            'products'      => $products,
            'price_range'   => $this->filterService->getPriceRange($category->id),
            'brands'        => $this->filterService->getAvailableBrands($category->id, $productIds),
            'filters'       => $this->filterService->getAggregatedAttributes($category->id, $productIds, $params),
            'product_types' => [], // Сюда можно добавить подкатегории, если нужно
        ];
    }
}
