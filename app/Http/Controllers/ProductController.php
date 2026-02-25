<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Inertia\{Inertia, Response};
use App\Services\CatalogService;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\ProductResource;
use App\Models\{Category, Product, Property};

class ProductController extends Controller
{

    public function __construct(
        protected CatalogService $catalogService
    )
    {
    }

    public function index(Category $category, Request $request): Response
    {
        $filters = (array)$request->input('filters', []);
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'newest');

        // 1. БАЗОВЫЕ ID ТОВАРОВ (только наличие в категории)
        $baseProductIds = Product::where('category_id', $category->id)
            ->whereHas('variants.stocks', fn($q) => $q->where('quantity', '>', 0))
            ->pluck('id');

        if ($baseProductIds->isEmpty()) {
            return $this->emptyResponse($category);
        }

        // 2. МЕТАДАННЫЕ ФИЛЬТРОВ
        $filterMeta = Property::whereHas('variantValues.variant.product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })
            ->with(['measure', 'variantValues' => function ($q) use ($category) {
                $q->whereHas('variant.product', fn($pq) => $pq->where('category_id', $category->id))
                    ->select('property_id', 'value', 'label', 'priority')
                    ->distinct();
            }])
            ->orderBy('priority')->get();

        // 3. ПОИСК ВАРИАНТОВ ДЛЯ ВЫДАЧИ (С учетом ЦЕНЫ и СВОЙСТВ)
        $finalVariantIds = DB::table('product_variants as pv')
            ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
            ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id') // Джоиним цены
            ->where('st.quantity', '>', 0)
            ->where('ps.price_type_id', 1)
            ->whereIn('pv.product_id', $baseProductIds)
            ->where(function ($query) use ($minPrice, $maxPrice) {
                if ($minPrice) $query->where('ps.amount', '>=', $minPrice);
                if ($maxPrice) $query->where('ps.amount', '<=', $maxPrice);
            })
            ->where(function ($query) use ($filters) {
                foreach ($filters as $slug => $values) {
                    $flatValues = collect($values)->flatten()->filter()->toArray();
                    if (empty($flatValues)) continue;

                    $query->whereIn('pv.id', function ($subQuery) use ($slug, $flatValues) {
                        $subQuery->select('pvp.variant_id')
                            ->from('product_variant_properties as pvp')
                            ->join('properties as prop', 'pvp.property_id', '=', 'prop.id')
                            ->where('prop.slug', $slug)
                            ->whereIn('pvp.value', $flatValues);
                    });
                }
            })
            ->distinct()->pluck('pv.id');

        // 4. УМНЫЙ РАСЧЕТ ДОСТУПНОСТИ (is_available)
        // Чтобы кнопки зачеркивались, когда цена выходит за рамки
        $smartFilters = $filterMeta->map(function ($prop) use ($filters, $baseProductIds, $minPrice, $maxPrice) {
            $currentSlug = strtolower($prop->slug);
            $otherFilters = collect($filters)->filter(fn($v, $k) => strtolower($k) !== $currentSlug && !empty($v));

            $query = DB::table('product_variants as pv')
                ->join('stocks as st', 'pv.id', '=', 'st.product_variant_id')
                ->join('prices as ps', 'pv.id', '=', 'ps.product_variant_id') // Учитываем цены здесь тоже!
                ->where('st.quantity', '>', 0)
                ->where('ps.price_type_id', 1)
                ->whereIn('pv.product_id', $baseProductIds);

            // Применяем фильтр по цене для "умности" кнопок
            if ($minPrice) $query->where('ps.amount', '>=', $minPrice);
            if ($maxPrice) $query->where('ps.amount', '<=', $maxPrice);

            foreach ($otherFilters as $slug => $values) {
                $flatValues = collect($values)->flatten()->filter()->toArray();
                $query->whereIn('pv.id', function ($subQuery) use ($slug, $flatValues) {
                    $subQuery->select('pvp2.variant_id')->from('product_variant_properties as pvp2')
                        ->join('properties as pr2', 'pvp2.property_id', '=', 'pr2.id')
                        ->where('pr2.slug', $slug)->whereIn('pvp2.value', $flatValues);
                });
            }

            $availableValues = $query->distinct()->join('product_variant_properties as pvp_final', 'pv.id', '=', 'pvp_final.variant_id')
                ->where('pvp_final.property_id', $prop->id)
                ->pluck('pvp_final.value')->map(fn($v) => (string)$v)->toArray();

            return [
                'name' => $prop->name,
                'slug' => $prop->slug,
                'unit' => $prop->measure?->symbol,
                'options' => $prop->variantValues->map(function ($v) use ($availableValues) {
                    return [
                        'value' => $v->value,
                        'label' => $v->label ?? $v->value,
                        'is_available' => in_array((string)$v->value, $availableValues, true),
                        'priority' => $v->priority
                    ];
                })->unique('value')->sortBy('priority')->values()
            ];
        });

        // 5. ФОРМИРОВАНИЕ ТОВАРОВ
        $productsQuery = Product::whereIn('id', function ($q) use ($finalVariantIds) {
            $q->select('product_id')->from('product_variants')->whereIn('id', $finalVariantIds);
        })
            ->with(['variants' => function ($vQuery) use ($finalVariantIds) {
                $vQuery->whereIn('id', $finalVariantIds)->with(['properties.property.measure', 'prices', 'stocks']);
            }]);

        // Сортировка (упрощенно)
        if ($sort === 'price_asc') $productsQuery->orderBy(DB::table('prices')->select('amount')->whereColumn('product_variant_id', 'product_variants.id')->limit(1), 'asc');
        else $productsQuery->latest();

        $products = $productsQuery->simplePaginate(12)->withQueryString();

        // 6. ДИАПАЗОН ЦЕН
        $priceRange = DB::table('prices')
            ->join('product_variants', 'prices.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.category_id', $category->id)
            ->where('prices.price_type_id', 1)
            ->selectRaw('MIN(amount) as min, MAX(amount) as max')->first();

        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => ['min' => floor($priceRange->min ?? 0), 'max' => ceil($priceRange->max ?? 10000)],
            'filters' => $smartFilters,
            'products' => ProductResource::collection($products),
            'active_filters' => (object)$filters,
            'current_sort' => $sort
        ]);
    }


    private function emptyResponse($category): Response
    {
        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => ['min' => 0, 'max' => 0],
            'filters' => [],
            'products' => ['data' => []],
            'active_filters' => (object)[],
        ]);
    }
}
