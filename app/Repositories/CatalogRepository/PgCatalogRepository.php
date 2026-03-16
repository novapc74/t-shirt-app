<?php

namespace App\Repositories\CatalogRepository;

use App\Services\Catalog\DTO\CategoryDataDto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PgCatalogRepository implements CatalogRepositoryInterface
{
    /**
     * Получает список уникальных типов фильтров (entity_type), доступных для конкретной категории.
     *
     * Логика работы:
     * 1. Собирает массив всех ID вариантов продуктов (product_variants), принадлежащих категории.
     * 2. Выполняет пересечение (оператор &&) с векторами фильтров в таблице filter_vectors.
     * 3. Возвращает только те типы фильтров (например, 'color', 'brand', 'size'), для которых
     *    в данной категории реально существуют товары.
     * 4. Результат кэшируется на 1 час для снижения нагрузки на БД.
     *
     * @param int $categoryId ID категории, для которой ищем доступные фильтры.
     *
     * @return array<int, string> Список уникальных строковых ключей фильтров (например, ['brand', 'color', 'size']).
     *                            Если товаров в категории нет, вернет пустой массив.
     *
     * @throws QueryException В случае ошибки выполнения SQL (например, отсутствие расширения intarray).
     */
    public function getAllowedFilterKeys(int $categoryId): array
    {
        return Cache::remember("allowed_keys_$categoryId", 3600, function () use ($categoryId) {
            $sql = "
            SELECT DISTINCT entity_type
            FROM filter_vectors
            WHERE entity_type NOT IN ('category', 'system')
                AND variant_ids && (
                    SELECT uniq(sort(array_agg(pv.id)::int4[]))
                    FROM product_variants AS pv
                    JOIN products AS p ON p.id = pv.product_id
                    WHERE p.category_id = :categoryId
            )
        ";

            $result = DB::select($sql, ['categoryId' => $categoryId]);

            return array_column($result, 'entity_type');
        });
    }


    public function getCategoryVector(int $categoryId): string
    {
        return DB::table('filter_vectors')->where('entity_type', 'category')->where('entity_id', $categoryId)->value(
            'variant_ids'
        ) ?? '{}';
    }

    public function getFilterDefinitions(): array
    {
        return Cache::remember('catalog_filter_definitions', 86400, function () {
            $data = [['id' => 1, 'slug' => 'price', 'title' => 'Цена', 'type' => 'range']];

            $tables = [
                ['slug' => 'brand', 'title' => 'Бренд', 'table' => 'brands', 'cols' => 'id, title'],
                ['slug' => 'color', 'title' => 'Цвет', 'table' => 'colors', 'cols' => 'id, title, slug, hex_code'],
                ['slug' => 'size', 'title' => 'Размер', 'table' => 'sizes', 'cols' => 'id, title, slug'],
                ['slug' => 'gender', 'title' => 'Гендер', 'table' => 'genders', 'cols' => 'id, title, slug'],
            ];

            foreach ($tables as $t) {
                $data[] = [
                    'id' => count($data) + 1,
                    'slug' => $t['slug'],
                    'title' => $t['title'],
                    'type' => 'checkbox',
                    'values' => DB::table($t['table'])->selectRaw($t['cols'])->get()->map(fn($v) => (array)$v)->toArray(
                    ),
                ];
            }

            foreach (DB::table('properties')->get() as $prop) {
                $data[] = [
                    'id' => (int)$prop->id + 100,
                    'slug' => $prop->slug,
                    'title' => $prop->title,
                    'type' => 'checkbox',
                    'values' => DB::table('property_values')->where('property_id', $prop->id)->select(
                        'id',
                        'value as title',
                        'slug'
                    )->get()->map(fn($v) => (array)$v)->toArray(),
                ];
            }

            return $data;
        });
    }

    public function getAggregatedCatalogData(
        int $categoryId,
        string $groupKeysRaw,
        string $groupVectorsRaw,
        string $categoryVector,
        string $variantIdsRaw,
        int $limit,
        int $offset
    ): object {
        return DB::selectOne(
            "
            WITH group_bases AS (
                SELECT unnest(:groupKeys::text[]) as g_type,
                       unnest(:groupVectors::text[])::int4[] as g_vector
            ),
            filter_counts AS (
                SELECT fv.entity_type, fv.entity_id,
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
                                pp.id, pp.title as name, pp.slug, b.title as brand, cat.title as category,
                                (SELECT MIN(price) FROM prices WHERE product_variant_id = ANY(:vIds::int4[]) AND product_variant_id IN (SELECT id FROM product_variants WHERE product_id = pp.id)) as min_price,
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
                'groupKeys' => $groupKeysRaw,
                'groupVectors' => $groupVectorsRaw,
                'catVector' => $categoryVector,
                'vIds' => $variantIdsRaw,
                'catId' => $categoryId,
                'limit' => $limit,
                'offset' => $offset,
            ]
        );
    }

    public function getCategoryData(int $categoryId): ?CategoryDataDto
    {
        $sql = <<<SQL
    SELECT
        c.id,
        c.title,
        c.slug,
        c.parent_id
    FROM categories c
    WHERE c.id = :categoryId;
SQL;

        $result = DB::selectOne($sql, ['categoryId' => $categoryId]);

        return !empty($result)
            ? CategoryDataDto::fromArray((array)$result)
            : null;
    }
}

