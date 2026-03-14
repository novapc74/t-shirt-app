<?php

namespace App\Repositories\CatalogRepository;

use Illuminate\Support\Facades\DB;

class PgFilterRepository implements FilterRepositoryInterface
{
    public function findMatchedVariantIds(array $filters, array $priceRange): array
    {
        $selectParts = [];
        $bindings = [];

        // Базовый слой
        $selectParts[] = "SELECT variant_ids FROM filter_vectors WHERE entity_type = 'system' AND entity_id = 1";

        // Группы
        foreach ($filters as $type => $ids) {
            $idsList = implode(',', array_map('intval', $ids));
            $selectParts[] = "SELECT COALESCE((SELECT array_cat_agg(variant_ids) FROM filter_vectors WHERE entity_type = '$type' AND entity_id IN ($idsList)), '{}'::int4[])";
        }

        // Цена
        if (!empty($priceRange)) {
            $selectParts[] = "SELECT COALESCE((SELECT array_cat_agg(variant_ids) FROM filter_vectors WHERE entity_type = 'price_range' AND entity_id >= :min_p AND entity_id <= :max_p), '{}'::int4[])";
            $bindings['min_p'] = $priceRange['min'];
            $bindings['max_p'] = $priceRange['max'];
        }

        $intersectChain = array_map(fn($sql) => "($sql)", $selectParts);
        $finalSql = "SELECT (".implode(' & ', $intersectChain).") as matched";

        $result = DB::selectOne($finalSql, $bindings);

        return $this->parsePostgresArray($result->matched ?? '{}');
    }

    private function parsePostgresArray(string $raw): array
    {
        $trimmed = trim($raw, '{}');

        return $trimmed === '' ? [] : array_map('intval', explode(',', $trimmed));
    }
}
