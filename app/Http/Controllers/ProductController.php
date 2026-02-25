<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Inertia\{Inertia, Response};
use App\Http\Resources\ProductResource;
use App\Models\{Category, Product, Property};

class ProductController extends Controller
{
    public function index(Category $category, Request $request): Response
    {
        // 1. Входящие данные
        $filters = (array)$request->input('filters', []);
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'newest');

        // 2. Базовые ID товаров категории в наличии
        $baseProductIds = Product::where('category_id', $category->id)
            ->whereHas('variants.stocks', fn($q) => $q->where('quantity', '>', 0))
            ->pluck('id');

        if ($baseProductIds->isEmpty()) {
            return $this->emptyResponse($category);
        }

        // 3. Метаданные свойств (Кэшируем структуру фильтров)
        $filterMeta = Property::whereHas('variantValues.variant.product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })
            ->with(['measure', 'variantValues' => function ($q) use ($category) {
                $q->whereHas('variant.product', fn($pq) => $pq->where('category_id', $category->id))
                    ->select('property_id', 'value', 'label', 'priority')->distinct();
            }])
            ->orderBy('priority')->get();

        // 4. ГЕНЕРАЦИЯ ЕДИНОГО ЗАПРОСА ДОСТУПНОСТИ (UNION ALL)
        // Мы строим массив подзапросов для каждой группы фильтров
        $unionQueries = [];
        $activeFilters = collect($filters)->map(fn($v) => collect($v)->flatten()->filter()->toArray())->filter();

        foreach ($filterMeta as $prop) {
            $currentSlug = strtolower($prop->slug);

            // Формируем "чужие" фильтры для текущей группы
            $otherFilters = $activeFilters->filter(fn($v, $k) => strtolower($k) !== $currentSlug);

            $subQuery = DB::table('product_variants as pv')
                ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
                ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
                ->join('product_variant_properties as pvp', 'pv.id', '=', 'pvp.variant_id')
                ->select(DB::raw("'{$prop->id}' as property_group_id"), 'pvp.value as available_value')
                ->where('st.quantity', '>', 0)
                ->where('ps.price_type_id', 1)
                ->whereIn('pv.product_id', $baseProductIds)
                ->where('pvp.property_id', $prop->id);

            // Ограничения по цене (влияют на все группы)
            if ($minPrice) $subQuery->where('ps.amount', '>=', $minPrice);
            if ($maxPrice) $subQuery->where('ps.amount', '<=', $maxPrice);

            // Применяем "чужие" фильтры к этой ветке UNION
            foreach ($otherFilters as $slug => $values) {
                $subQuery->whereIn('pv.id', function ($q) use ($slug, $values) {
                    $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                        ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                        ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', $values);
                });
            }

            $unionQueries[] = $subQuery->distinct();
        }

        // Объединяем всё в один запрос
        $firstQuery = array_shift($unionQueries);
        foreach ($unionQueries as $query) {
            $firstQuery->unionAll($query);
        }

        // Выполняем ОДИН запрос и группируем результат в памяти PHP
        $availabilityMap = $firstQuery->get()
            ->groupBy('property_group_id')
            ->map(fn($group) => $group->pluck('available_value')->toArray());

        // 5. СБОРКА SMART FILTERS
        $smartFilters = $filterMeta->map(function ($prop) use ($availabilityMap) {
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

        // 6. ПОИСК ТОВАРОВ ДЛЯ ВЫДАЧИ
        $finalVariantIds = DB::table('product_variants as pv')
            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
            ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id')
            ->where('st.quantity', '>', 0)
            ->where('ps.price_type_id', 1)
            ->whereIn('pv.product_id', $baseProductIds)
            ->where(function ($query) use ($minPrice, $maxPrice) {
                if ($minPrice) $query->where('ps.amount', '>=', $minPrice);
                if ($maxPrice) $query->where('ps.amount', '<=', $maxPrice);
            })
            ->where(function ($query) use ($activeFilters) {
                foreach ($activeFilters as $slug => $values) {
                    $query->whereIn('pv.id', function ($q) use ($slug, $values) {
                        $q->select('pvp_sub.variant_id')->from('product_variant_properties as pvp_sub')
                            ->join('properties as pr_sub', 'pvp_sub.property_id', '=', 'pr_sub.id')
                            ->where('pr_sub.slug', $slug)->whereIn('pvp_sub.value', $values);
                    });
                }
            })->distinct()->pluck('pv.id');

        $products = Product::whereIn('id', fn($q) => $q->select('product_id')->from('product_variants')->whereIn('id', $finalVariantIds))
            ->with([
                'variants' => fn($q) => $q->whereIn('id', $finalVariantIds)->with(['properties.property.measure', 'prices', 'stocks'])
            ])
            ->latest()
            ->simplePaginate(12)->withQueryString();

        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => $this->getPriceRange($category->id),
            'filters' => $smartFilters,
            'products' => ProductResource::collection($products),
            'active_filters' => (object)$filters,
            'current_sort' => $sort
        ]);
    }

    private function getPriceRange($categoryId): array
    {
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
    }

    private function emptyResponse($category): Response
    {
        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => [
                'min' => 0, 'max' => 0
            ],
            'filters' => [],
            'products' => [
                'data' => []
            ],
            'active_filters' => (object)[],
        ]);
    }
}
