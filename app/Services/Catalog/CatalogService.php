<?php

namespace App\Services\Catalog;

use App\Models\{Product, Category};
use App\Http\Requests\CatalogFilterRequest;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Pagination\LengthAwarePaginator;

readonly class CatalogService
{
    public function __construct(
        private FilterService $filterService
    ) {}

    /**
     * Основной метод получения каталога с умными фильтрами
     */
    public function getCategoryCatalog(int $categoryId, CatalogFilterRequest $request): array
    {
        $category = Category::findOrFail($categoryId);

        // 1. Кэшируем доступные типы фильтров для этой категории (через пересечение векторов)
        $allowedKeys = Cache::remember("allowed_keys_$categoryId", 3600, function () use ($categoryId) {
            return DB::table('filter_vectors')
                ->whereRaw('variant_ids && (
                    SELECT uniq(sort(array_agg(pv.id)::int4[]))
                    FROM product_variants as pv
                    JOIN products as p ON p.id = pv.product_id
                    WHERE p.category_id = ?
                )', [$categoryId])
                ->distinct()
                ->pluck('entity_type')
                ->toArray();
        });

        $dto = CatalogFilterRequestDto::fromRequest($request, $allowedKeys);

        // 2. Получаем ID вариантов, прошедших итоговую фильтрацию
        $variantIds = $this->filterService->getMatchedVariantIds($dto);

        // 3. Считаем УМНЫЕ СЧЕТЧИКИ (Smart Filters) с логикой OR внутри группы
        $counters = $this->getSmartFilterCounters($categoryId, $dto, $allowedKeys);

        // 4. Получаем ID товаров и загружаем их с пагинацией
        $productIds = !empty($variantIds)
            ? DB::table('product_variants')->whereIn('id', $variantIds)->distinct()->pluck('product_id')->toArray()
            : [];

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('category_id', $categoryId)
            ->with([
                'brand',
                'category',
                'variants' => function ($q) use ($variantIds) {
                    $q->whereIn('id', $variantIds)->with(['color', 'size', 'gender', 'prices', 'stocks']);
                }
            ])
            ->paginate(12);

        // Если товаров нет, возвращаем пустую структуру, но с категориями и фильтрами
        if ($products->isEmpty()) {
            return $this->formatEmptyResponse($category, $dto, $categoryId, $allowedKeys);
        }

        return [
            'category' => $category->title,
            'products' => $this->formatProducts($products),
            'filters'  => $this->formatFilters($counters, $dto, $variantIds)
        ];
    }

    /**
     * Логика умных счетчиков: исключаем текущую группу при расчете её же цифр
     */
    private function getSmartFilterCounters(int $categoryId, CatalogFilterRequestDto $dto, array $allowedKeys): array
    {
        $categoryVector = DB::table('filter_vectors')
            ->where('entity_type', 'category')
            ->where('entity_id', $categoryId)
            ->value('variant_ids') ?? '{}';

        $results = [];

        foreach ($allowedKeys as $type) {
            if (in_array($type, ['category', 'system'])) continue;

            // ПРАВИЛО: Для расчета счетчиков группы мы ИГНОРИРУЕМ выбор внутри этой группы.
            // Это позволяет "Синему" не обнулять "Красный".
            $baseVariantIds = $this->filterService->getMatchedVariantIds($dto, $type);
            $baseSet = '{' . implode(',', $baseVariantIds ?: []) . '}';

            $counts = DB::table('filter_vectors')
                ->where('entity_type', $type)
                ->select('entity_id')
                ->selectRaw('icount((variant_ids & ?::int4[]) & ?::int4[]) as total', [
                    $baseSet,
                    $categoryVector
                ])
                ->whereRaw('variant_ids && ?::int4[]', [$categoryVector])
                ->get()
                ->pluck('total', 'entity_id')
                ->toArray();

            $results[$type] = $counts;
        }

        return $results;
    }


    /**
     * Форматирование продуктов для фронтенда
     */
    private function formatProducts(LengthAwarePaginator $paginated): array
    {
        return [
            'data' => collect($paginated->items())->map(fn($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'slug'      => $p->slug,
                'brand'     => $p->brand?->title,
                'category'  => $p->category?->title,
                'min_price' => (float)$p->variants->min('price'),
                'variants'  => $p->variants->map(fn($v) => [
                    'id'     => $v->id,
                    'sku'    => $v->sku,
                    'price'  => (float)$v->prices->where('price_type_id', 1)->first()?->price,
                    'color'  => $v->color ? (array)$v->color->only('id', 'title', 'slug', 'hex_code') : null,
                    'size'   => $v->size ? (array)$v->size->only('id', 'title', 'slug') : null,
                    'gender' => $v->gender ? (array)$v->gender->only('id', 'title', 'slug') : null,
                ])
            ]),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ]
        ];
    }

    /**
     * Сборка и форматирование фильтров
     */
    private function formatFilters(array $counters, CatalogFilterRequestDto $dto, array $variantIds): array
    {
        $definitions = $this->getFilterDefinitions();

        $priceStats = empty($variantIds)
            ? (object)['min' => 0, 'max' => 0]
            : DB::table('prices')->whereIn('product_variant_id', $variantIds)->selectRaw('MIN(price) as min, MAX(price) as max')->first();

        return array_map(function ($filter) use ($counters, $dto, $priceStats) {
            $slug = $filter['slug'];

            if ($slug === 'price') {
                $filter['values'] = [
                    'min' => (float)($priceStats->min ?? 0),
                    'max' => (float)($priceStats->max ?? 0)
                ];
                return $filter;
            }

            $activeIds = $dto->getFilters($slug);

            $filter['values'] = array_map(function ($val) use ($counters, $slug, $activeIds) {
                $val = (array)$val;
                $id = $val['id'];
                $count = $counters[$slug][$id] ?? 0;

                return array_merge($val, [
                    'count'        => $count,
                    'is_available' => $count > 0,
                    'is_active'    => in_array($id, $activeIds)
                ]);
            }, (array)$filter['values']);

            return $filter;
        }, $definitions);
    }

    /**
     * Справочники фильтров (минимальное потребление памяти)
     */
    private function getFilterDefinitions(): array
    {
        return Cache::remember('catalog_filter_definitions', 86400, function () {
            $data = [];
            $data[] = ['id' => 1, 'slug' => 'price', 'title' => 'Цена', 'type' => 'range'];

            $tables = [
                ['slug' => 'brand',  'title' => 'Бренд',  'table' => 'brands',  'cols' => 'id, title'],
                ['slug' => 'color',  'title' => 'Цвет',   'table' => 'colors',  'cols' => 'id, title, slug, hex_code'],
                ['slug' => 'size',   'title' => 'Размер', 'table' => 'sizes',   'cols' => 'id, title, slug'],
                ['slug' => 'gender', 'title' => 'Гендер', 'table' => 'genders', 'cols' => 'id, title, slug'],
            ];

            foreach ($tables as $t) {
                $data[] = [
                    'id'     => count($data) + 1,
                    'slug'   => $t['slug'],
                    'title'  => $t['title'],
                    'type'   => 'checkbox',
                    'values' => DB::table($t['table'])->selectRaw($t['cols'])->get()->map(fn($v) => (array)$v)->toArray()
                ];
            }

            $dynamicProps = DB::table('properties')->select('id', 'slug', 'title')->get();
            foreach ($dynamicProps as $prop) {
                $data[] = [
                    'id' => $prop->id + 100,
                    'slug' => $prop->slug,
                    'title' => $prop->title,
                    'type' => 'checkbox',
                    'values' => DB::table('property_values')
                        ->where('property_id', $prop->id)
                        ->select('id', 'value as title', 'slug')
                        ->get()->map(fn($v) => (array)$v)->toArray()
                ];
            }

            return $data;
        });
    }

    private function formatEmptyResponse(Category $category, CatalogFilterRequestDto $dto, int $categoryId, array $allowedKeys): array
    {
        $counters = $this->getSmartFilterCounters($categoryId, $dto, $allowedKeys);
        return [
            'category' => $category->title,
            'products' => ['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 12]],
            'filters'  => $this->formatFilters($counters, $dto, [])
        ];
    }
}
