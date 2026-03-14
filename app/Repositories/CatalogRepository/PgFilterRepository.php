<?php

namespace App\Repositories\CatalogRepository;

use Illuminate\Support\Facades\DB;

class PgFilterRepository implements FilterRepositoryInterface
{
    private array $bindings = [];

    public function findMatchedVariantIds(array $filters, array $priceRange): array
    {
        $this->bindings = []; // Сброс при каждом вызове
        $selectParts = [];

        // 1. Слой наличия
        $selectParts[] = $this->getSystemStockSql();

        // 2. Слои категориальных фильтров
        foreach ($filters as $type => $ids) {
            $selectParts[] = $this->getFilterGroupSql($type, (array)$ids);
        }

        // 3. Слой цены
        if (!empty($priceRange)) {
            $selectParts[] = $this->getPriceRangeSql($priceRange['min'], $priceRange['max']);
        }

        // Сборка финального пересечения через оператор &
        $intersectChain = array_map(fn($sql) => "($sql)", $selectParts);
        $finalSql = "SELECT (" . implode(' & ', $intersectChain) . ") as matched";

        $result = DB::selectOne($finalSql, $this->bindings);

        return $this->parsePostgresArray($result->matched ?? '{}');
    }

    /**
     * Слой наличия цены
     * @return string
     */
    private function getSystemStockSql(): string
    {
        return "SELECT variant_ids FROM filter_vectors WHERE entity_type = 'system' AND entity_id = 1";
    }

    private function getFilterGroupSql(string $type, array $ids): string
    {
        $idsList = implode(',', array_map('intval', $ids));

        return "SELECT COALESCE((
                    SELECT array_cat_agg(variant_ids)
                    FROM filter_vectors
                    WHERE entity_type = '$type' AND entity_id IN ($idsList)
                ), '{}'::int4[])";
    }

    /**
     * Слой цены
     * @param int $min
     * @param int $max
     *
     * @return string
     */
    private function getPriceRangeSql(int $min, int $max): string
    {
        $this->bindings['min_p'] = $min;
        $this->bindings['max_p'] = $max;

        return "SELECT COALESCE((
                    SELECT array_cat_agg(variant_ids)
                    FROM filter_vectors
                    WHERE entity_type = 'price_range'
                      AND entity_id >= :min_p
                      AND entity_id <= :max_p
                ), '{}'::int4[])";
    }

    private function parsePostgresArray(string $raw): array
    {
        $trimmed = trim($raw, '{}');
        return $trimmed === '' ? [] : array_map('intval', explode(',', $trimmed));
    }
}
