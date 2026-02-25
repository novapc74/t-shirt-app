<?php

namespace App\Services;

use App\Models\{Category, Product, Property};
use App\Filters\{CategoryFilter, PriceFilter, AttributeFilter, StockFilter};
use Illuminate\Pagination\Paginator;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Database\Eloquent\Builder;

class CatalogService
{
    public function getCategoryData(Category $category): ?array
    {
        $filters = (array) request('filters', []);
        $sort = request('sort', 'newest');
        $categoryIds = $category->getAllSubcategoryIds();

        // 1. Базовый запрос для сетки товаров
        $query = app(Pipeline::class)
            ->send(Product::query()->whereIn('category_id', $categoryIds))
            ->through([
                CategoryFilter::class,
                PriceFilter::class,
                AttributeFilter::class,
                StockFilter::class,
            ])
            ->thenReturn();

        // 2. ID товаров для расчета фильтров (стабильный сайдбар)
        $categoryProductIds = Product::whereIn('category_id', $categoryIds)->pluck('id');
        if ($categoryProductIds->isEmpty()) return null;

        $products = $this->getFilteredProducts($query, $sort, $filters);

        return [
            'products' => $products,
            'meta'     => [
                'filters'     => $this->getSmartFilters($category, $filters, $categoryProductIds),
                'price_range' => $this->getPriceRange($category, $categoryIds),
            ],
        ];
    }

    private function getFilteredProducts(Builder $query, string $sort, array $filters): Paginator
    {
        return $query->with(['category', 'variants' => function ($vQuery) {
            $vQuery->whereHas('stocks', fn($s) => $s->where('quantity', '>', 0))
                ->with(['properties.property.measure', 'prices', 'stocks']);
        }])
            ->when(in_array($sort, ['price_asc', 'price_desc']), function($q) use ($sort) {
                $q->join('product_variants as pvs', 'products.id', '=', 'pvs.product_id')
                    ->join('prices as ps', 'pvs.id', '=', 'ps.product_variant_id')
                    ->where('ps.price_type_id', 1)
                    ->select('products.*', DB::raw('MIN(ps.amount) as sort_price'))
                    ->groupBy('products.id')
                    ->orderBy('sort_price', $sort === 'price_asc' ? 'asc' : 'desc');
            }, fn($q) => $q->latest('products.created_at'))
            ->simplePaginate(12)->withQueryString();
    }

    private function getSmartFilters($category, $filters, $baseProductIds): Collection
    {
        $filterMeta = Property::whereHas('variantValues.variant.product', fn($q) => $q->whereIn('category_id', $category->getAllSubcategoryIds()))
            ->with(['measure', 'variantValues' => function($q) {
                $q->select('property_id', 'value', 'label', 'priority')->distinct()->orderBy('priority');
            }])
            ->orderBy('priority')->get();

        return $filterMeta->map(function ($prop) use ($filters, $baseProductIds) {
            // Для определения доступности опций в группе (например, Размер)
            // мы учитываем ВСЕ ДРУГИЕ активные фильтры (Цвет, Пол)
            $otherFilters = collect($filters)->forget([$prop->slug, 'min_price', 'max_price'])->filter()->toArray();

            $query = DB::table('product_variant_properties as pvp')
                ->join('product_variants as pv', 'pvp.variant_id', '=', 'pv.id')
                ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
                ->where('st.quantity', '>', 0)
                ->whereIn('pv.product_id', $baseProductIds)
                ->where('pvp.property_id', $prop->id);

            // Если выбраны другие фильтры, проверяем, чтобы вариация им соответствовала
            foreach ($otherFilters as $slug => $values) {
                if (empty($values)) continue;
                $query->whereIn('pv.id', function($sub) use ($slug, $values) {
                    $sub->select('pvp2.variant_id')
                        ->from('product_variant_properties as pvp2')
                        ->join('properties as pr2', 'pvp2.property_id', '=', 'pr2.id')
                        ->where('pr2.slug', $slug)
                        ->whereIn('pvp2.value', (array)$values);
                });
            }

            $availableValues = $query->distinct()->pluck('pvp.value')->toArray();

            return [
                'name' => $prop->name,
                'slug' => $prop->slug,
                'unit' => $prop->measure?->symbol,
                'options' => $prop->variantValues->map(fn($v) => [
                    'value' => $v->value,
                    'label' => $v->label ?? $v->value,
                    'is_available' => in_array($v->value, $availableValues),
                    'priority' => $v->priority
                ])->unique('value')->sortBy('priority')->values()
            ];
        });
    }

    private function getPriceRange($category, array $categoryIds): array
    {
        $res = DB::table('prices')
            ->join('product_variants', 'prices.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereIn('products.category_id', $categoryIds)
            ->where('prices.price_type_id', 1)
            ->selectRaw('MIN(amount) as min, MAX(amount) as max')->first();

        return ['min' => floor($res->min ?? 0), 'max' => ceil($res->max ?? 0)];
    }
}
