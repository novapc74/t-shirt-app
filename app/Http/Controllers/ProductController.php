<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Services\CatalogService;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{

    public function __construct(
        protected CatalogService $catalogService
    )
    {
    }

    public function index(Category $category): Response
    {
        $category->load('childrenRecursive');
        $result = $this->catalogService->getCategoryData($category);

        if (!$result) {
            return Inertia::render('Catalog/CategoryPage', $this->emptyState($category));
        }

        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => $result['meta']['price_range'],
            'filters' => $result['meta']['filters'],
            'products' => ProductResource::collection($result['products'])->response()->getData(true),
            'active_filters' => (object)request('filters', []),
        ]);
    }

    private function emptyState(Category $category): array
    {
        return [
            'category' => $category->name,
            'price_range' => ['min' => 0, 'max' => 0],
            'filters' => [],
            'products' => ['data' => []],
        ];
    }
//    public function index(Category $category, Request $request): Response
//    {
//        $filters = (array) $request->input('filters', []);
//        $minPrice = $request->input('min_price');
//        $maxPrice = $request->input('max_price');
//        $sort = $request->input('sort', 'newest');
//
//        // 1. БАЗОВЫЕ ID ТОВАРОВ (Категория + Цена + Наличие)
//        // Используем индекс [category_id, id]
//        $baseProductIds = Product::where('category_id', $category->id)
//            ->whereHas('variants', function ($vQuery) use ($minPrice, $maxPrice) {
//                $vQuery->whereHas('stocks', fn($s) => $s->where('quantity', '>', 0));
//
//                if ($minPrice || $maxPrice) {
//                    $vQuery->whereHas('prices', function ($p) use ($minPrice, $maxPrice) {
//                        $p->where('price_type_id', 1); // Розница
//                        if ($minPrice) $p->where('amount', '>=', $minPrice);
//                        if ($maxPrice) $p->where('amount', '<=', $maxPrice);
//                    });
//                }
//            })->pluck('id');
//
//        if ($baseProductIds->isEmpty()) {
//            return $this->emptyResponse($category);
//        }
//
//        // 2. МЕТАДАННЫЕ ФИЛЬТРОВ (С учетом приоритета групп)
//        $filterMeta = Cache::remember("cat_meta_v5_{$category->id}", 3600, function () use ($category) {
//            return Property::whereHas('variantValues.variant.product', function ($q) use ($category) {
//                $q->where('category_id', $category->id);
//            })
//                ->with(['measure', 'variantValues' => function ($q) use ($category) {
//                    $q->whereHas('variant.product', fn($pq) => $pq->where('category_id', $category->id))
//                        ->select('property_id', 'value', 'label', 'priority')
//                        ->distinct();
//                }])
//                ->orderBy('priority') // Сортировка ГРУПП (Цвет, Размер)
//                ->get();
//        });
//
//        // 3. УМНЫЙ РАСЧЕТ ДОСТУПНОСТИ (is_available)
//        $smartFilters = $filterMeta->map(function ($prop) use ($filters, $baseProductIds) {
//            $otherFilters = collect($filters)->forget($prop->slug)->toArray();
//
//            // Ищем значения, которые физически есть в наличии при текущих ВЫБРАННЫХ остальных фильтрах
//            $availableValues = DB::table('product_variant_properties as pvp')
//                ->join('product_variants as pv', 'pvp.variant_id', '=', 'pv.id')
//                ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
//                ->where('st.quantity', '>', 0)
//                ->whereIn('pv.product_id', $baseProductIds)
//                ->where('pvp.property_id', $prop->id)
//                ->where(function($query) use ($otherFilters) {
//                    foreach ($otherFilters as $slug => $values) {
//                        $query->whereExists(function ($q) use ($slug, $values) {
//                            $q->select(DB::raw(1))
//                                ->from('product_variant_properties as pvp2')
//                                ->join('properties as pr2', 'pvp2.property_id', '=', 'pr2.id')
//                                ->whereColumn('pvp2.variant_id', 'pv.id')
//                                ->where('pr2.slug', $slug)
//                                ->whereIn('pvp2.value', (array)$values);
//                        });
//                    }
//                })
//                ->distinct()->pluck('pvp.value')->toArray();
//
//            return [
//                'name' => $prop->name,
//                'slug' => $prop->slug,
//                'unit' => $prop->measure?->symbol,
//                'options' => $prop->variantValues->map(function ($v) use ($availableValues) {
//                    return [
//                        'value'        => $v->value,
//                        'label'        => $v->label ?? $v->value,
//                        'is_available' => in_array($v->value, $availableValues),
//                        'priority'     => $v->priority
//                    ];
//                })
//                    ->unique('value')
//                    ->sortBy('priority') // Сортировка ЗНАЧЕНИЙ (XS -> S -> M)
//                    ->values()
//            ];
//        });
//
//        // 4. ПОИСК ВАРИАНТОВ ДЛЯ ВЫДАЧИ
//        $finalVariantIds = DB::table('product_variants as pv')
//            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
//            ->where('st.quantity', '>', 0)
//            ->whereIn('pv.product_id', $baseProductIds)
//            ->where(function($query) use ($filters) {
//                foreach ($filters as $slug => $values) {
//                    $query->whereExists(function ($q) use ($slug, $values) {
//                        $q->select(DB::raw(1))
//                            ->from('product_variant_properties as pvp')
//                            ->join('properties as prop', 'pvp.property_id', '=', 'prop.id')
//                            ->whereColumn('pvp.variant_id', 'pv.id')
//                            ->where('prop.slug', $slug)
//                            ->whereIn('pvp.value', (array)$values);
//                    });
//                }
//            })->distinct()->pluck('pv.id');
//
//        // 5. ФОРМИРОВАНИЕ ТОВАРОВ С СОРТИРОВКОЙ
//        $productsQuery = Product::whereIn('products.id', function($q) use ($finalVariantIds) {
//            $q->select('product_id')->from('product_variants')->whereIn('id', $finalVariantIds);
//        })
//            ->with(['variants' => function ($vQuery) use ($finalVariantIds) {
//                $vQuery->whereIn('id', $finalVariantIds)
//                    ->with(['properties.property.measure', 'prices', 'stocks']);
//            }]);
//
//        // Применяем сортировку
//        if ($sort === 'price_asc' || $sort === 'price_desc') {
//            $productsQuery->join('product_variants as pvs', 'products.id', '=', 'pvs.product_id')
//                ->join('prices as ps', 'pvs.id', '=', 'ps.product_variant_id')
//                ->where('ps.price_type_id', 1)
//                ->select('products.*', DB::raw('MIN(ps.amount) as sort_price'))
//                ->groupBy('products.id')
//                ->orderBy('sort_price', $sort === 'price_asc' ? 'asc' : 'desc');
//        } else {
//            $productsQuery->latest('products.created_at');
//        }
//
//        $products = $productsQuery->simplePaginate(12)->withQueryString();
//
//        // 6. ДИАПАЗОН ЦЕН (Кэшируем для производительности)
//        $priceRange = Cache::remember("cat_prices_v5_{$category->id}", 600, function () use ($category) {
//            return DB::table('prices')
//                ->join('product_variants', 'prices.product_variant_id', '=', 'product_variants.id')
//                ->join('products', 'product_variants.product_id', '=', 'products.id')
//                ->join('stocks', 'product_variants.id', '=', 'stocks.product_variant_id')
//                ->where('products.category_id', $category->id)
//                ->where('prices.price_type_id', 1)
//                ->where('stocks.quantity', '>', 0)
//                ->selectRaw('MIN(amount) as min, MAX(amount) as max')->first();
//        });
//
//        return Inertia::render('Catalog/CategoryPage', [
//            'category'       => $category->name,
//            'price_range'    => [
//                'min' => floor($priceRange->min ?? 0),
//                'max' => ceil($priceRange->max ?? 10000)
//            ],
//            'filters'        => $smartFilters,
//            'products'       => ProductResource::collection($products)->response()->getData(true),
//            'active_filters' => (object) $filters,
//            'current_sort'   => $sort
//        ]);
//    }
//
//    private function emptyResponse($category): \Inertia\Response
//    {
//        return Inertia::render('Catalog/CategoryPage', [
//            'category'    => $category->name,
//            'price_range' => ['min' => 0, 'max' => 0],
//            'filters'     => [],
//            'products'    => ['data' => []],
//            'active_filters' => (object) [],
//        ]);
//    }
}
