<?php

namespace App\Services\Catalog;

use App\Models\PriceType;
use Illuminate\Support\Facades\DB;

class FilterService
{
    private array $queryParams = [];
    private array $cteParts = [];
    private bool $hasPriceFilter = false;
    private array $facetTypes = ['brand', 'color', 'size', 'prop_val', 'gender'];

    public function getCatalogData(int $categoryId, array $filters = [], bool $isOrStrategy = false): array
    {
        $this->queryParams = [];
        $this->cteParts = [];
        $activeFilters = array_filter($filters, fn($v) => !empty($v));

        // 1. Базовый вектор категории
        $this->addCategoryVectorAsCte($categoryId);

        // 2. Подготовка CTE для активных фильтров
        $this->addPriceFilterAsCte($activeFilters);
        $this->addOtherFilterPropertiesAsCte($activeFilters);

        // 3. Сборка SQL для счетчиков (фасетов)
        $unionFacets = $this->solveAllFacetsSql($activeFilters);

        // Полное пересечение (для товаров)
        $finalIdsIntersect = $this->buildIntersection($activeFilters, null, $isOrStrategy);
        // Пересечение без учета самой цены (для ползунка)
        $priceScope = $this->buildIntersection($activeFilters, 'price');

        $cteSql = $this->solveCteParts();

        // 4. Формирование финального SQL
        $finalSql = <<<EOL
        $cteSql
        SELECT
            ($finalIdsIntersect) as ids,
            (SELECT json_build_object(
                'min', COALESCE(MIN(p.price), 0),
                'max', COALESCE(MAX(p.price), 0))
            FROM prices p
            JOIN product_variants pv ON p.product_variant_id = pv.id
            WHERE pv.product_id = ANY(($priceScope)::int[])
                AND p.price_type_id = :price_type
            ) as price_range,
            (SELECT json_object_agg(f_type, f_data)
            FROM (
                SELECT f_type, json_object_agg(entity_id, cnt) as f_data
                FROM (
                    SELECT f_type, entity_id, cnt
                    FROM ($unionFacets) t
                    WHERE cnt > 0
                ) f_inner
                GROUP BY f_type
            ) f_outer
            ) as facets
        EOL;

        $this->queryParams['price_type'] = PriceType::RETAIL_PRICE_TYPE;
        $result = DB::selectOne($finalSql, $this->queryParams);

        return [
            'ids' => $this->parsePgArray($result->ids ?? ''),
            'facets' => json_decode($result->facets ?? '{}', true),
            'price_range' => json_decode($result->price_range ?? '{"min":0,"max":0}', true),
        ];
    }

    private function solveAllFacetsSql(?array $activeFilters = []): string
    {
        $allFacetsSql = [];
        foreach ($this->facetTypes as $type) {
            $scopeSql = $this->buildIntersection($activeFilters, $type);

            // Пересекаем товары (product_ids & scope), разворачиваем их в строки (unnest)
            // и считаем количество связанных с ними вариантов в таблице product_variants
            $allFacetsSql[] = <<<SQL
SELECT
    '$type' AS f_type,
    fv.entity_id,
    (
        SELECT COUNT(*)
        FROM product_variants pv
        WHERE pv.product_id = ANY(fv.product_ids & ($scopeSql))
    ) AS cnt
FROM filter_vectors fv
WHERE fv.entity_type = '$type'
SQL;
        }

        return implode("\nUNION ALL\n", $allFacetsSql);
    }


//    private function solveAllFacetsSql(?array $activeFilters = []): string
//    {
//        $allFacetsSql = [];
//        foreach ($this->facetTypes as $type) {
//            $scopeSql = $this->buildIntersection($activeFilters, $type);
//            $allFacetsSql[] = <<<SQL
//SELECT
//    '$type' AS f_type,
//    entity_id, icount(product_ids & ($scopeSql)) AS cnt
//FROM filter_vectors
//WHERE entity_type = '$type'
//SQL;
//        }
//
//        return implode("\nUNION ALL\n", $allFacetsSql);
//    }

    /**
     * Логика пересечения векторов (&).
     * Если передан $excludeType, фильтры этой группы игнорируются.
     * Это позволяет видеть все доступные бренды, даже если один из них выбран.
     */
    private function buildIntersection(
        ?array $activeFilters = [],
        ?string $excludeType = null,
        bool $isOrStrategy = false
    ): string {
        // Категория — это базис, она всегда обязательна (AND)
        $categoryPart = "(SELECT product_ids FROM cat_v)";

        $filterParts = [];

        // Добавляем цену
        if ($this->hasPriceFilter && $excludeType !== 'price') {
            $filterParts[] = "(SELECT v FROM price_v)";
        }

        // Добавляем остальные активные фильтры
        foreach ($activeFilters as $type => $value) {
            if ($type !== 'price' && $type !== $excludeType && in_array($type, $this->facetTypes)) {
                $filterParts[] = "(SELECT v FROM {$type}_v)";
            }
        }

        if (empty($filterParts)) {
            return $categoryPart;
        }

        // Определяем оператор между фильтрами
        $operator = $isOrStrategy ? ' | ' : ' & ';

        // Склеиваем фильтры между собой выбранной стратегией
        $filtersCombined = implode($operator, $filterParts);

        // ВАЖНО: Результат фильтров всегда пересекаем (&) с категорией
        return "($categoryPart & ($filtersCombined))";
    }


    private function solveCteParts(): string
    {
        return empty($this->cteParts) ? "" : "WITH ".implode(",\n", $this->cteParts);
    }

    private function addCategoryVectorAsCte(int $categoryId): void
    {
        $this->queryParams['cat_id'] = $categoryId;

        $ctePart = <<<EOL
cat_v AS (SELECT product_ids FROM filter_vectors WHERE entity_type = 'category' AND entity_id = :cat_id)
EOL;
        $this->addCtePart($ctePart);
    }

    private function addPriceFilterAsCte(array $activeFilters): void
    {
        $min = $activeFilters['price']['min'] ?? null;
        $max = $activeFilters['price']['max'] ?? null;

        if (!$min && !$max) {
            return;
        }

        $this->hasPriceFilter = true;
        $this->queryParams['price_type'] = PriceType::RETAIL_PRICE_TYPE;
        $condition = ["p.price_type_id = :price_type"];

        if ($min) {
            $condition[] = "p.price >= :min_price";
            $this->queryParams['min_price'] = (float)$min;
        }

        if ($max) {
            $condition[] = "p.price <= :max_price";
            $this->queryParams['max_price'] = (float)$max;
        }

        $conditions = implode(" AND ", $condition);
        $ctePart = <<<SQL
price_v AS (
    SELECT public.sort(public.uniq(array_agg(DISTINCT pv.product_id)::int[])) as v
    FROM prices p
    JOIN product_variants pv ON p.product_variant_id = pv.id
    WHERE $conditions)
SQL;

        $this->addCtePart($ctePart);
    }

    private function addOtherFilterPropertiesAsCte(array $activeFilters): void
    {
        foreach ($activeFilters as $type => $ids) {
            if ($type === 'price' || !in_array($type, $this->facetTypes)) {
                continue;
            }

            $paramName = "ids_$type";
            $this->queryParams[$paramName] = '{'.implode(',', array_map('intval', (array)$ids)).'}';

            $ctePart = <<<SQL
{$type}_v AS (
    SELECT public.sort(public.uniq(array_agg(el::int)::int[])) as v
    FROM filter_vectors, unnest(product_ids) el
    WHERE entity_type = '$type' AND entity_id = ANY(CAST(:$paramName AS int[])))
SQL;

            $this->addCtePart($ctePart);
        }
    }

    private function parsePgArray(?string $pgArray): array
    {
        if (!$pgArray || $pgArray === '{}') {
            return [];
        }

        return array_map('intval', explode(',', trim($pgArray, '{}')));
    }

    private function addCtePart(string $ctePart): void
    {
        $this->cteParts[] = $ctePart;
    }
}
