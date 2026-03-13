<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Http\Requests\CatalogFilterRequest;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CatalogService
{
    private FilterService $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    public function getCategoryCatalog(int $categoryId, CatalogFilterRequest $request): array
    {
        $category = Category::findOrFail($categoryId);

        // 1. Кэшируем доступные типы фильтров для этой категории
        $allowedKeys = Cache::remember("allowed_keys_$categoryId", 3600, function () use ($categoryId) {
            return DB::table('filter_vectors')
                ->whereRaw('variant_ids && (
                    SELECT uniq(sort(array_agg(pv.id)::int4[]))
                    FROM product_variants as pv
                    JOIN products as p ON p.id = pv.product_id
                    WHERE p.category_id = ?
                )', [$categoryId])
                ->pluck('entity_type')
                ->toArray();
        });

        $dto = CatalogFilterRequestDto::fromRequest($request, $allowedKeys);

        // 2. Итоговые ID вариантов для списка товаров (логика AND между группами)
        $variantIds = $this->filterService->getMatchedVariantIds($dto);
        $vIdsRaw = '{' . implode(',', $variantIds ?: []) . '}';

        // 3. Подготовка данных для "умных счетчиков" (логика OR внутри группы)
        $groupKeys = [];
        $groupVectors = [];
        foreach ($allowedKeys as $type) {
            if (in_array($type, ['category', 'system'])) continue;
            $groupKeys[] = $type;
            $baseIds = $this->filterService->getMatchedVariantIds($dto, $type);
            $groupVectors[] = '{' . implode(',', $baseIds ?: []) . '}';
        }

        // Превращаем массивы ключей в формат Postgres {a,b,c} для unnest
        $groupKeysRaw = '{' . implode(',', $groupKeys) . '}';
        $groupVectorsRaw = '{' . implode(',', array_map(fn($v) => '"' . $v . '"', $groupVectors)) . '}';

        $categoryVector = DB::table('filter_vectors')
            ->where('entity_type', 'category')
            ->where('entity_id', $categoryId)
            ->value('variant_ids') ?? '{}';

        $page = (int)$request->get('page', 1);
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        // 4. Единый SQL запрос (Чистый Postgres)
        $dbResult = DB::selectOne("
            WITH
            group_bases AS (
                SELECT unnest(:groupKeys::text[]) as g_type,
                       unnest(:groupVectors::text[])::int4[] as g_vector
            ),
            filter_counts AS (
                SELECT
                    fv.entity_type,
                    fv.entity_id,
                    icount((gb.g_vector & fv.variant_ids) & :catVector::int4[]) as total
                FROM filter_vectors fv
                JOIN group_bases gb ON gb.g_type = fv.entity_type
                WHERE fv.variant_ids && :catVector::int4[]
            ),
            paged_products AS (
                SELECT p.id, p.title, p.slug, p.brand_id, p.category_id, count(*) OVER() as full_count
                FROM products p
                WHERE p.category_id = :catId
                  AND EXISTS (
                      SELECT 1 FROM product_variants pv
                      WHERE pv.product_id = p.id AND pv.id = ANY(:vIds::int4[])
                  )
                ORDER BY p.id DESC
                LIMIT :limit OFFSET :offset
            )
            SELECT
                json_build_object(
                    'total', COALESCE((SELECT MAX(full_count) FROM paged_products), 0),
                    'items', (
                        SELECT COALESCE(json_agg(p_row), '[]'::json) FROM (
                            SELECT
                                pp.id, pp.title as name, pp.slug,
                                b.title as brand,
                                cat.title as category,
                                (SELECT MIN(price) FROM prices
                                 WHERE product_variant_id = ANY(:vIds::int4[])
                                   AND product_variant_id IN (SELECT id FROM product_variants WHERE product_id = pp.id)) as min_price,
                                (
                                    SELECT json_agg(v_row) FROM (
                                        SELECT
                                            v.id, v.sku,
                                            (SELECT price FROM prices WHERE product_variant_id = v.id AND price_type_id = 1 LIMIT 1) as price,
                                            json_build_object('id', c.id, 'title', c.title, 'slug', c.slug, 'hex_code', c.hex_code) as color,
                                            json_build_object('id', s.id, 'title', s.title, 'slug', s.slug) as size,
                                            json_build_object('id', g.id, 'title', g.title, 'slug', g.slug) as gender
                                        FROM product_variants v
                                        LEFT JOIN colors c ON c.id = v.color_id
                                        LEFT JOIN sizes s ON s.id = v.size_id
                                        LEFT JOIN genders g ON g.id = v.gender_id
                                        WHERE v.product_id = pp.id AND v.id = ANY(:vIds::int4[])
                                    ) v_row
                                ) as variants
                            FROM paged_products pp
                            LEFT JOIN brands b ON b.id = pp.brand_id
                            LEFT JOIN categories cat ON cat.id = pp.category_id
                        ) p_row
                    )
                ) as products_json,
                (SELECT COALESCE(json_agg(fc), '[]'::json) FROM filter_counts fc) as counters_json,
                (SELECT json_build_object('min', COALESCE(MIN(price),0), 'max', COALESCE(MAX(price),0))
                 FROM prices WHERE product_variant_id = ANY(:vIds::int4[])) as price_stats
        ", [
            'groupKeys'    => $groupKeysRaw,
            'groupVectors' => $groupVectorsRaw,
            'catVector'    => $categoryVector,
            'vIds'         => $vIdsRaw,
            'catId'        => $categoryId,
            'limit'        => $perPage,
            'offset'       => $offset
        ]);

        // 5. Декодирование и сборка финального ответа
        $productsData = json_decode($dbResult->products_json, true);
        $counters = $this->mapCounters(json_decode($dbResult->counters_json, true));
        $priceStats = json_decode($dbResult->price_stats, true);

        return [
            'category' => $category->title,
            'products' => [
                'data' => $productsData['items'] ?? [],
                'meta' => [
                    'total'        => (int)($productsData['total'] ?? 0),
                    'current_page' => $page,
                    'last_page'    => (int)ceil(($productsData['total'] ?? 0) / $perPage),
                    'per_page'     => $perPage,
                ]
            ],
            'filters' => $this->assembleFilters($counters, $dto, $priceStats)
        ];
    }

    private function mapCounters(?array $raw): array
    {
        $res = [];
        foreach ($raw ?? [] as $item) {
            $res[$item['entity_type']][$item['entity_id']] = $item['total'];
        }
        return $res;
    }

    private function assembleFilters(array $counters, CatalogFilterRequestDto $dto, array $priceStats): array
    {
        $definitions = Cache::remember('catalog_filter_definitions', 86400, fn() => $this->getFilterDefinitions());

        return array_map(function ($filter) use ($counters, $dto, $priceStats) {
            $slug = $filter['slug'];
            if ($slug === 'price') {
                $filter['values'] = [
                    'min' => (float)($priceStats['min'] ?? 0),
                    'max' => (float)($priceStats['max'] ?? 0)
                ];
                return $filter;
            }
            $activeIds = $dto->getFilters($slug);
            $filter['values'] = array_map(function ($val) use ($counters, $slug, $activeIds) {
                $count = $counters[$slug][$val['id']] ?? 0;
                return array_merge($val, [
                    'count'        => (int)$count,
                    'is_available' => $count > 0,
                    'is_active'    => in_array($val['id'], $activeIds)
                ]);
            }, $filter['values'] ?? []);
            return $filter;
        }, $definitions);
    }

    private function getFilterDefinitions(): array
    {
        $data = [['id' => 1, 'slug' => 'price', 'title' => 'Цена', 'type' => 'range']];
        $tables = [
            ['slug' => 'brand',  'title' => 'Бренд',  'table' => 'brands',  'cols' => 'id, title'],
            ['slug' => 'color',  'title' => 'Цвет',   'table' => 'colors',  'cols' => 'id, title, slug, hex_code'],
            ['slug' => 'size',   'title' => 'Размер', 'table' => 'sizes',   'cols' => 'id, title, slug'],
            ['slug' => 'gender', 'title' => 'Гендер', 'table' => 'genders', 'cols' => 'id, title, slug'],
        ];
        foreach ($tables as $t) {
            $data[] = [
                'id' => count($data) + 1, 'slug' => $t['slug'], 'title' => $t['title'], 'type' => 'checkbox',
                'values' => DB::table($t['table'])->selectRaw($t['cols'])->get()->map(fn($v) => (array)$v)->toArray()
            ];
        }
        foreach (DB::table('properties')->get() as $prop) {
            $data[] = [
                'id' => (int)$prop->id + 100, 'slug' => $prop->slug, 'title' => $prop->title, 'type' => 'checkbox',
                'values' => DB::table('property_values')->where('property_id', $prop->id)
                    ->select('id', 'value as title', 'slug')->get()->map(fn($v) => (array)$v)->toArray()
            ];
        }
        return $data;
    }
}
