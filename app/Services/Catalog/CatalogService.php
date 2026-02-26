<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB};
use App\Models\{Product, Property, Category, ProductType, Brand};
use App\Services\Catalog\DTO\ProductFilterParams;

class CatalogService
{
    public function getCategoryCatalog(Category $category, ProductFilterParams $params): ?array
    {
        // 1. Базовые ID товаров категории в наличии
        $initialProductIds = Cache::remember("cat_base_ids_{$category->id}", now()->addMinutes(10), function () use ($category) {
            return Product::where('category_id', $category->id)
                ->whereHas('variants.stocks', fn($q) => $q->where('quantity', '>', 0))
                ->pluck('id');
        });

        if ($initialProductIds->isEmpty()) return null;

        // 2. Метаданные свойств
        $filterMeta = $this->getFilterMeta($category->id);

        // 3. УМНЫЙ РАСЧЕТ ДОСТУПНОСТИ (UNION ALL)
        $availabilityMap = $this->calculateAvailability($initialProductIds, $filterMeta, $params);

        // 4. Фильтрация базовых ID продуктов (для финальной выдачи товаров)
        $query = Product::whereIn('id', $initialProductIds);
        if (!empty($params->productTypes)) {
            $query->whereIn('product_type_id', function ($q) use ($params) {
                $q->select('id')->from('product_types')->whereIn('slug', $params->productTypes);
            });
        }
        if (!empty($params->brands)) {
            $query->whereIn('brand_id', function ($q) use ($params) {
                $q->select('id')->from('brands')->whereIn('slug', $params->brands);
            });
        }
        $baseProductIds = $query->pluck('id');

        // 5. Поиск финальных Variant IDs для выдачи
        $finalVariantIds = $this->getFinalVariantIds($baseProductIds, $params);

        // 6. Загрузка товаров
        $products = Product::whereIn('id', function ($q) use ($finalVariantIds) {
            $q->select('product_id')->from('product_variants')->whereIn('id', $finalVariantIds);
        })
            ->with([
                'brand',
                'productType',
                'variants' => function ($q) use ($finalVariantIds) {
                    $q->whereIn('id', $finalVariantIds)
                        ->with([
                            'properties' => function($pq) {
                                // Сортируем свойства внутри каждого варианта
                                $pq->orderBy('priority', 'asc');
                            },
                            'properties.property.measure',
                            'prices',
                            'stocks'
                        ]);
                }
            ])
            ->latest()
            ->simplePaginate(12);

        return [
            'price_range'   => $this->getPriceRange($category->id),
            'filters'       => $this->formatSmartFilters($filterMeta, $availabilityMap),
            'product_types' => $this->getProductTypes($category->id, $initialProductIds, $params, $availabilityMap),
            'brands'        => $this->getBrands($category->id, $initialProductIds, $params, $availabilityMap),
            'products'      => $products
        ];
    }

    private function calculateAvailability($initialProductIds, $filterMeta, ProductFilterParams $params): Collection
    {
        $unionQueries = [];

        // А. Доступность ТИПОВ
        $unionQueries[] = $this->buildAvailabilitySubQuery($initialProductIds, $params, 'type');
        // Б. Доступность БРЕНДОВ
        $unionQueries[] = $this->buildAvailabilitySubQuery($initialProductIds, $params, 'brand');
        // В. Доступность СВОЙСТВ
        foreach ($filterMeta as $prop) {
            $unionQueries[] = $this->buildAvailabilitySubQuery($initialProductIds, $params, 'property', $prop->id);
        }

        $firstQuery = array_shift($unionQueries);
        foreach ($unionQueries as $query) $firstQuery->unionAll($query);

        return $firstQuery->get()
            ->groupBy('group_key')
            ->map(fn($group) => $group->pluck('val')->map(fn($v) => (string)$v)->unique()->toArray());
    }

    private function buildAvailabilitySubQuery($initialIds, $params, $target, $targetId = null)
    {
        $query = DB::table('product_variants as pv')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
            ->whereIn('p.id', $initialIds)
            ->where('st.quantity', '>', 0)
            ->where('ps.price_type_id', 1);

        if ($params->minPrice) $query->where('ps.amount', '>=', $params->minPrice);
        if ($params->maxPrice) $query->where('ps.amount', '<=', $params->maxPrice);

        // Исключаем "себя" из фильтрации (Self-exclusion)
        if ($target !== 'type' && !empty($params->productTypes)) {
            $query->whereIn('p.product_type_id', function($q) use ($params) {
                $q->select('id')->from('product_types')->whereIn('slug', $params->productTypes);
            });
        }
        if ($target !== 'brand' && !empty($params->brands)) {
            $query->whereIn('p.brand_id', function($q) use ($params) {
                $q->select('id')->from('brands')->whereIn('slug', $params->brands);
            });
        }

        // Фильтрация по свойствам
        foreach ($params->filters as $slug => $values) {
            if (empty($values)) continue;
            $currentPropId = Cache::remember("prop_id_$slug", 3600, fn() => Property::where('slug', $slug)->value('id'));
            if ($target === 'property' && $targetId == $currentPropId) continue;

            $query->whereIn('pv.id', function ($q) use ($slug, $values) {
                $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                    ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                    ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', (array)$values);
            });
        }

        // ::text обязателен для PostgreSQL в UNION ALL
        if ($target === 'type') {
            return $query->select(DB::raw("'type' as group_key"), DB::raw("p.product_type_id::text as val"))->distinct();
        }
        if ($target === 'brand') {
            return $query->select(DB::raw("'brand' as group_key"), DB::raw("p.brand_id::text as val"))->distinct();
        }
        return $query->join('product_variant_properties as pvp', 'pv.id', '=', 'pvp.variant_id')
            ->where('pvp.property_id', $targetId)
            ->select(DB::raw("'prop_' || pvp.property_id as group_key"), DB::raw("pvp.value::text as val"))->distinct();
    }

    private function getProductTypes($categoryId, $initialIds, $params, $availMap): Collection
    {
        $availableIds = $availMap->get('type', []);
        return ProductType::whereHas('products', fn($q) => $q->whereIn('products.id', $initialIds))
            ->withCount(['products as variants_count' => function ($q) use ($initialIds) {
                $q->whereIn('products.id', $initialIds)->join('product_variants', 'products.id', '=', 'product_variants.product_id');
            }])->get()->map(fn($t) => [
                'name' => "{$t->name} ({$t->variants_count})",
                'slug' => $t->slug,
                'is_selected' => in_array($t->slug, $params->productTypes),
                'is_available' => in_array((string)$t->id, $availableIds)
            ]);
    }

    private function getBrands($categoryId, $initialIds, $params, $availMap): Collection
    {
        $availableIds = $availMap->get('brand', []);
        return Brand::whereHas('products', fn($q) => $q->whereIn('products.id', $initialIds))
            ->withCount(['products as variants_count' => function ($q) use ($initialIds) {
                $q->whereIn('products.id', $initialIds)->join('product_variants', 'products.id', '=', 'product_variants.product_id');
            }])->get()->map(fn($b) => [
                'name' => "{$b->name} ({$b->variants_count})",
                'slug' => $b->slug,
                'is_selected' => in_array($b->slug, $params->brands),
                'is_available' => in_array((string)$b->id, $availableIds)
            ]);
    }

    private function formatSmartFilters($filterMeta, $availabilityMap): Collection
    {
        return $filterMeta->map(function ($prop) use ($availabilityMap) {
            $availableValues = $availabilityMap->get("prop_{$prop->id}", []);
            return [
                'name' => $prop->name, 'slug' => $prop->slug, 'unit' => $prop->measure?->symbol,
                'options' => $prop->variantValues->map(fn($v) => [
                    'value' => $v->value, 'label' => $v->label ?? $v->value, 'priority' => $v->priority,
                    'is_available' => in_array((string)$v->value, $availableValues, true),
                ])->unique('value')->sortBy('priority')->values()
            ];
        });
    }

    private function getFinalVariantIds($baseProductIds, ProductFilterParams $params): Collection
    {
        $query = DB::table('product_variants as pv')
            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
            ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
            ->where('st.quantity', '>', 0)->where('ps.price_type_id', 1)->whereIn('pv.product_id', $baseProductIds);

        if ($params->minPrice) $query->where('ps.amount', '>=', $params->minPrice);
        if ($params->maxPrice) $query->where('ps.amount', '<=', $params->maxPrice);

        foreach ($params->filters as $slug => $values) {
            if (empty($values)) continue;
            $query->whereIn('pv.id', function ($q) use ($slug, $values) {
                $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                    ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                    ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', (array)$values);
            });
        }
        return $query->distinct()->pluck('pv.id');
    }

    private function getFilterMeta($categoryId): Collection
    {
        return Cache::remember("category_filters_meta_{$categoryId}", now()->addDay(), function () use ($categoryId) {
            return Property::whereHas('variantValues.variant.product', fn($q) => $q->where('category_id', $categoryId))
                ->with(['measure', 'variantValues' => fn($q) => $q->whereHas('variant.product', fn($pq) => $pq->where('category_id', $categoryId))
                    ->select('property_id', 'value', 'label', 'priority')->distinct()])->orderBy('priority')->get();
        });
    }

    public function getPriceRange($categoryId): array
    {
        return Cache::remember("category_price_range_{$categoryId}", now()->addDay(), function () use ($categoryId) {
            $data = DB::table('prices as ps')->join('product_variants as pv', 'ps.product_variant_id', '=', 'pv.id')
                ->join('products as p', 'pv.product_id', '=', 'p.id')->where('p.category_id', $categoryId)
                ->where('ps.price_type_id', 1)->selectRaw('MIN(amount) as min, MAX(amount) as max')->first();
            return ['min' => floor($data->min ?? 0), 'max' => ceil($data->max ?? 0)];
        });
    }
}
