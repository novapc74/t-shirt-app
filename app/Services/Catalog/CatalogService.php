<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\{Product, Property, Category};
use App\Services\Catalog\DTO\ProductFilterParams;

class CatalogService
{
    /**
     * Основной метод получения данных для каталога
     *
     * @param Category $category
     * @param ProductFilterParams $params
     * @return array|null
     */
    public function getCategoryCatalog(Category $category, ProductFilterParams $params): ?array
    {
        // 1. Базовые ID товаров категории в наличии
        $baseProductIds = Cache::remember("category_base_ids_{$category->id}", now()->addMinutes(10), function () use ($category) {
            return Product::where('category_id', $category->id)
                ->whereHas('variants.stocks', fn($q) => $q->where('quantity', '>', 0))
                ->pluck('id');
        });


        if ($baseProductIds->isEmpty()) {
            return null;
        }

        // 2. Метаданные свойств
        $filterMeta = $this->getFilterMeta($category->id);

        // 3. Карта доступности (UNION ALL запрос)
        $availabilityMap = $this->calculateAvailability($baseProductIds, $filterMeta, $params);

        // 4. Поиск финальных Variant IDs для выдачи
        $finalVariantIds = $this->getFinalVariantIds($baseProductIds, $params);

        // 5. Загрузка товаров с пагинацией
        $products = Product::whereIn('id', function ($q) use ($finalVariantIds) {
            $q->select('product_id')->from('product_variants')->whereIn('id', $finalVariantIds);
        })
            ->with([
                'variants' => fn($q) => $q->whereIn('id', $finalVariantIds)
                    ->with(['properties.property.measure', 'prices', 'stocks'])
            ])
            ->latest()
            ->simplePaginate(12)
            ->withQueryString();

        return [
            'price_range' => $this->getPriceRange($category->id),
            'filters' => $this->formatSmartFilters($filterMeta, $availabilityMap),
            'products' => $products
        ];
    }


    /**
     * Поиск ID вариантов, которые подходят под текущие фильтры
     *
     * @param $baseProductIds
     * @param ProductFilterParams $params
     * @return Collection
     */
    private function getFinalVariantIds($baseProductIds, ProductFilterParams $params): Collection
    {
        $activeFilters = collect($params->filters)
            ->map(fn($v) => collect($v)->flatten()->filter()->toArray())
            ->filter();

        $query = DB::table('product_variants as pv')
            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
            ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
            ->where('st.quantity', '>', 0)
            ->where('ps.price_type_id', 1)
            ->whereIn('pv.product_id', $baseProductIds);

        if ($params->minPrice) $query->where('ps.amount', '>=', $params->minPrice);
        if ($params->maxPrice) $query->where('ps.amount', '<=', $params->maxPrice);

        foreach ($activeFilters as $slug => $values) {
            $query->whereIn('pv.id', function ($q) use ($slug, $values) {
                $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                    ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                    ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', $values);
            });
        }

        return $query->distinct()->pluck('pv.id');
    }

    /**
     * Расчет доступности опций фильтра (UNION ALL)
     *
     * @param $baseProductIds
     * @param $filterMeta
     * @param ProductFilterParams $params
     * @return Collection
     */
    private function calculateAvailability($baseProductIds, $filterMeta, ProductFilterParams $params): Collection
    {
        $activeFilters = collect($params->filters)
            ->map(fn($v) => collect($v)->flatten()->filter()->toArray())
            ->filter();

        $unionQueries = [];

        foreach ($filterMeta as $prop) {
            $currentSlug = strtolower($prop->slug);
            $otherFilters = $activeFilters->filter(fn($v, $k) => strtolower($k) !== $currentSlug);

            $subQuery = DB::table('product_variants as pv')
                ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
                ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
                ->join('product_variant_properties as pvp', 'pv.id', '=', 'pvp.variant_id')
                ->select(DB::raw("$prop->id as property_group_id"), 'pvp.value as available_value')
                ->where('st.quantity', '>', 0)
                ->where('ps.price_type_id', 1)
                ->whereIn('pv.product_id', $baseProductIds)
                ->where('pvp.property_id', $prop->id);

            if ($params->minPrice) $subQuery->where('ps.amount', '>=', $params->minPrice);
            if ($params->maxPrice) $subQuery->where('ps.amount', '<=', $params->maxPrice);

            foreach ($otherFilters as $slug => $values) {
                $subQuery->whereIn('pv.id', function ($q) use ($slug, $values) {
                    $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                        ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                        ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', $values);
                });
            }

            $unionQueries[] = $subQuery->distinct();
        }

        if (empty($unionQueries)) return collect();

        $firstQuery = array_shift($unionQueries);
        foreach ($unionQueries as $query) {
            $firstQuery->unionAll($query);
        }

        return $firstQuery->get()
            ->groupBy('property_group_id')
            ->map(fn($group) => $group->pluck('available_value')->map(fn($v) => (string)$v)->toArray());
    }

    /**
     * Получение структуры фильтров для категории
     *
     * @param $categoryId
     * @return Collection
     */
    private function getFilterMeta($categoryId): Collection
    {
        // Кэшируем на 24 часа (или до тех пор, пока не обновим товар)
        return Cache::remember("category_filters_meta_{$categoryId}", now()->addDay(), function () use ($categoryId) {
            return Property::whereHas('variantValues.variant.product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })
                ->with(['measure', 'variantValues' => function ($q) use ($categoryId) {
                    $q->whereHas('variant.product', fn($pq) => $pq->where('category_id', $categoryId))
                        ->select('property_id', 'value', 'label', 'priority')
                        ->distinct();
                }])
                ->orderBy('priority')
                ->get();
        });
    }

    /**
     * Форматирование фильтров для фронтенда
     *
     * @param $filterMeta
     * @param $availabilityMap
     * @return Collection
     */
    private function formatSmartFilters($filterMeta, $availabilityMap): Collection
    {
        return $filterMeta->map(function ($prop) use ($availabilityMap) {
            $availableValues = $availabilityMap->get($prop->id, []);
            return [
                'name' => $prop->name,
                'slug' => $prop->slug,
                'unit' => $prop->measure?->symbol,
                'options' => $prop->variantValues->map(
                    fn($v) => [
                        'value' => $v->value,
                        'label' => $v->label ?? $v->value,
                        'is_available' => in_array((string)$v->value, $availableValues, true),
                        'priority' => $v->priority
                    ]
                )->unique('value')->sortBy('priority')->values()
            ];
        });
    }

    /**
     * Расчет диапазона цен
     *
     * @param $categoryId
     * @return array
     */
    public function getPriceRange($categoryId): array
    {
        return Cache::remember("category_price_range_{$categoryId}", now()->addDay(), function () use ($categoryId) {
            $data = DB::table('prices as ps')
                ->join('product_variants as pv', 'ps.product_variant_id', '=', 'pv.id')
                ->join('products as p', 'pv.product_id', '=', 'p.id')
                ->where('p.category_id', $categoryId)
                ->selectRaw('MIN(amount) as min, MAX(amount) as max')
                ->first();

            return [
                'min' => floor($data->min ?? 0),
                'max' => ceil($data->max ?? 1000000)
            ];
        });
    }
}

