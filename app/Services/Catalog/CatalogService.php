<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Catalog\DTO\ProductFilterParams;

readonly class CatalogService
{
    private const  PER_PAGE = 12;

    public function __construct(private FilterService $filterService)
    {
    }

    /**
     * Максимально быстрый метод получения каталога
     */
    public function getCategoryCatalog(int $categoryId, ProductFilterParams $params): array
    {
        $globalData = Cache::remember(
            "cat_facets_$categoryId", 3600, fn() => $this->filterService->getCatalogData($categoryId)
        );

        $globalFacets = $globalData['facets'] ?? [];

        $normalizedFilters = $this->getNormalizedPropertyFilters($params->getFilters());

        $activeData = $this->filterService->getCatalogData($categoryId, $normalizedFilters, $params->isOrStrategy());

        $matchedIds = $activeData['ids'] ?? [];
        $activeCounts = $activeData['facets'] ?? [];

        // 5. Пагинация и загрузка товаров
        $paginator = $this->paginateIds($matchedIds, $params);
        $currentPageIds = $paginator->pluck('product_id')->toArray();

        $products = $this->loadProducts(
            $currentPageIds,
            $params->getFilters('price') ?? []
        );


        $activePriceRange = $activeData['price_range'] ?? ['min' => 0, 'max' => 0];

        return [
            'category' => DB::table('categories')->where('id', $categoryId)->value('title'),
            'products' => [
                'data' => $products,
                'meta' => [
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => self::PER_PAGE,
                ],
            ],
            'filters' => $this->addFilter(
                $globalFacets,
                $activeCounts,
                $params->getFilters(),
                $activePriceRange
            ),
        ];
    }

    private function getNormalizedPropertyFilters(array $filters): array
    {
        $propMapping = $this->getPropertyMapping();

        $propValIds = (array)($filters['prop_val'] ?? []);

        foreach ($filters as $slug => $values) {
            if (isset($propMapping[$slug])) {
                // Добавляем ID значений из material/season в общий стек prop_val
                $propValIds = array_merge($propValIds, (array)$values);
                // Удаляем именной ключ, чтобы FilterService не создавал лишних CTE
                unset($filters[$slug]);
            }
        }

        if (!empty($propValIds)) {
            $filters['prop_val'] = array_unique($propValIds);
        }

        return $filters;
    }

    /**
     * Вспомогательный метод для получения маппинга слагов свойств
     */
    private function getPropertyMapping(): array
    {
        return Cache::remember('properties_slug_to_id', 86400, function () {
            return DB::table('properties')->pluck('id', 'slug')->toArray();
        });
    }


    private function addFilter(array $globalFacets, array $activeCounts, array $paramsFilters, $activePriceRange): array
    {
        $filters = [];

        $currentMin = $paramsFilters['price']['min'] ?? $activePriceRange['min'];
        $currentMax = $paramsFilters['price']['max'] ?? $activePriceRange['max'];

        $price = [
            'id' => 1,
            'slug' => 'price',
            'title' => 'Цена',
            'type' => 'range',
            'values' => [
                'min' => (float)$currentMin,
                'max' => (float)$currentMax,
            ],
        ];

        $filters[] = $price;
        $mapping = [
            'brand' => ['priority' => 2, 'title' => 'Бренд'],
            'color' => ['priority' => 3, 'title' => 'Цвет'],
            'size' => ['priority' => 4, 'title' => 'Размер'],
            'gender' => ['priority' => 5, 'title' => 'Гендер'],
        ];

        $lastFilterId = 0;
        $filterKeys = array_keys($mapping);

        foreach ($globalFacets as $key => $value) {
            if (in_array($key, $filterKeys)) {
                // Извлекаем ID, которые пользователь выбрал для этого типа фильтра
                $selectedForThisType = (array)($paramsFilters[$key] ?? []);

                $filters[] = [
                    'id' => $mapping[$key]['priority'],
                    'slug' => $key,
                    'title' => $mapping[$key]['title'],
                    'type' => 'checkbox',
                    'values' => $this->formatDictionaryFast(
                        $key.'s',
                        $value ?? [],
                        $activeCounts[$key] ?? [],
                        $selectedForThisType
                    ),
                ];
                $lastFilterId = max($lastFilterId, $mapping[$key]['priority']);
            }
        }

        // Обработка динамических свойств (Properties)
        $selectedProps = (array)($paramsFilters['prop_val'] ?? []);
        $properties = $this->formatPropsFast(
            $globalFacets['prop_val'] ?? [],
            $activeCounts['prop_val'] ?? [],
            $lastFilterId,
            $selectedProps
        );

        foreach ($properties as $value) {
            $filters[] = $value;
        }

        // Сортировка по приоритету (ID фильтра)
        usort($filters, fn($a, $b) => $a['id'] <=> $b['id']);

        return $filters;
    }

    /**
     * Загрузка товаров с вариантами. Не кешируем, это фильтры.
     */
    private function loadProducts(array $ids, array $priceFilter = []): array
    {
        if (empty($ids)) return [];

        $min = (float)($priceFilter['min'] ?? 0);
        $max = (float)($priceFilter['max'] ?? 9999999);

        $pgIds = '{' . implode(',', $ids) . '}';

        $results = DB::select("
        WITH sorted_ids AS (
            SELECT id, ord
            FROM unnest(CAST(:ids AS int[])) WITH ORDINALITY AS t(id, ord)
        ),
        filtered_variants AS (
            SELECT
                pv.product_id,
                json_agg(json_build_object(
                    'id', pv.id,
                    'sku', pv.sku,
                    'price', pr.price,
                    -- Добавляем priority для каждого справочника
                    'color', json_build_object(
                        'id', cl.id, 'title', cl.title, 'slug', cl.slug,
                        'priority', COALESCE(cl.priority, 0), 'hex_code', cl.hex_code
                    ),
                    'size', json_build_object(
                        'id', sz.id, 'title', sz.title, 'slug', sz.slug,
                        'priority', COALESCE(sz.priority, 0)
                    ),
                    'gender', json_build_object(
                        'id', gn.id, 'title', gn.title, 'slug', gn.slug,
                        'priority', COALESCE(gn.priority, 0)
                    )
                ) ORDER BY sz.priority ASC, cl.priority ASC) as variants_json,
                MIN(pr.price) as min_price_val
            FROM product_variants pv
            JOIN prices pr ON pr.product_variant_id = pv.id
            LEFT JOIN colors cl ON pv.color_id = cl.id
            LEFT JOIN sizes sz ON pv.size_id = sz.id
            LEFT JOIN genders gn ON pv.gender_id = gn.id
            WHERE pv.product_id = ANY(CAST(:ids AS int[]))
              AND pr.price >= :min
              AND pr.price <= :max
              AND pr.price_type_id = 1
            GROUP BY pv.product_id
        )
        SELECT
            p.id,
            p.title as name,
            p.slug,
            b.title as brand,
            c.title as category,
            COALESCE(fv.min_price_val, 0) as min_price,
            COALESCE(fv.variants_json, '[]') as variants
        FROM sorted_ids s
        JOIN products p ON p.id = s.id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN filtered_variants fv ON fv.product_id = p.id
        ORDER BY s.ord
    ", [
            'ids' => $pgIds,
            'min' => $min,
            'max' => $max
        ]);

        foreach ($results as $key => $item) {
            $results[$key] = (array) $item;
            $results[$key]['variants'] = json_decode($results[$key]['variants'], true) ?? [];
            $results[$key]['min_price'] = (float)$results[$key]['min_price'];
        }

        return $results;
    }

    /**
     * Форматирование свойств (Properties)
     */
    private function formatPropsFast(
        array $allValues,
        array $activeCounts,
        int $lastFilterId,
        array $selectedIds = []
    ): array {
        if (empty($allValues)) {
            return [];
        }

        $valueIds = array_keys($allValues);

        $properties = Cache::remember(
            'all_properties_structure', 86400, function () {
            return DB::table('properties as p')->join('property_values as v', 'p.id', '=', 'v.property_id')->select(
                'p.id',
                'p.title',
                'p.slug',
                'v.id as val_id',
                'v.value',
                'v.slug as val_slug'
            )->get()->groupBy('id');
        }
        );

        $result = [];
        foreach ($properties as $items) {
            $first = $items->first();
            $values = [];

            foreach ($items as $item) {
                // Если это значение свойства присутствует в глобальных фасетах категории
                if (in_array($item->val_id, $valueIds)) {
                    $count = $activeCounts[$item->val_id] ?? 0;
                    $values[] = [
                        'id' => $item->val_id,
                        'slug' => $item->val_slug,
                        'title' => $item->value,
                        'count' => $count,
                        'is_available' => (bool)$count > 0,
                        'is_active' => in_array($item->val_id, $selectedIds),
                    ];
                }
            }

            if (empty($values)) {
                continue;
            }

            $result[] = [
                'id' => ++$lastFilterId,
                'title' => $first->title,
                'slug' => $first->slug,
                'type' => 'checkbox',
                'values' => $values,
            ];
        }

        return $result;
    }

    /**
     * Универсальное форматирование простых справочников (Бренды, Цвета и т.д.)
     */
    private function formatDictionaryFast(
        string $table,
        array $allIds,
        array $activeCounts,
        array $selectedIds = []
    ): array {
        if (empty($allIds)) {
            return [];
        }

        $ids = array_keys($allIds);
        $data = Cache::remember(
            "dict_$table", 86400, fn() => DB::table($table)->get()->keyBy('id')
        );

        $result = [];
        foreach ($ids as $id) {
            if (!$item = $data->get($id)) {
                continue;
            }
            $count = $activeCounts[$id] ?? 0;
            $itemResult = [
                'id' => $id,
                'title' => $item->title ?? $item->name,
                'count' => $activeCounts[$id] ?? 0,
                // Флаг активен только если ID есть в массиве из URL/Запроса
                'is_available' => (bool)$count > 0,
                'is_active' => in_array($id, $selectedIds),
            ];

            if (isset($item->hex_code)) {
                $itemResult['hex'] = $item->hex_code;
            }

            $result[] = $itemResult;
        }

        return $result;
    }

    private function paginateIds(array $matchedIds, ProductFilterParams $params): LengthAwarePaginator
    {
        if (empty($matchedIds)) {
            return new LengthAwarePaginator(collect(), 0, self::PER_PAGE);
        }

        return DB::table('product_price_index')->whereIn('product_id', $matchedIds)->when(
            $params->getSort() === 'price_asc', fn($q) => $q->orderBy('min_price', 'asc')
        )->when(
            $params->getSort() === 'price_desc', fn($q) => $q->orderBy('min_price', 'desc')
        )->unless(
            in_array($params->getSort(), ['price_asc', 'price_desc']), fn($q) => $q->orderBy('product_id', 'desc')
        )->paginate(self::PER_PAGE);
    }
}
