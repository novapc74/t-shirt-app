<?php

namespace App\Services\Catalog;

use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use Illuminate\Support\Facades\DB;

class FilterService
{
    /**
     * Возвращает массив ID вариантов, прошедших фильтры.
     * @param string|null $excludeType Группа, которую нужно исключить (для умных счетчиков)
     */
    public function getMatchedVariantIds(CatalogFilterRequestDto $params, ?string $excludeType = null): array
    {
        $filters = $params->getFilters();

        // 1. Базовый слой — наличие (всегда AND)
        $stockSql = "SELECT COALESCE(variant_ids, '{}'::int4[]) as vids FROM filter_vectors WHERE entity_type = 'system' AND entity_id = 1";
        $fromClauses = ["($stockSql) AS stock(vids)"];
        $intersectChain = ["stock.vids"];

        // 2. Свойства (Характеристики)
        foreach ($filters as $type => $ids) {
            // Исключаем группу, если считаем её же счетчики (логика OR для UI)
            if ($type === $excludeType || empty($ids) || $type === 'price') continue;

            $idsList = implode(',', array_map('intval', (array)$ids));

            // Внутри ГРУППЫ всегда OR (объединение)
            $subSql = "SELECT COALESCE(array_cat_agg(variant_ids), '{}'::int4[]) as vids FROM filter_vectors WHERE entity_type = '$type' AND entity_id IN ($idsList)";

            $idx = count($fromClauses);
            $fromClauses[] = "($subSql) AS g$idx(vids)";
            $intersectChain[] = "g$idx.vids";
        }

        // 3. Цена (Всегда OR внутри диапазона)
        if ($params->hasPriceFilter() && $excludeType !== 'price') {
            $min = (int)($params->getMinPrice() ?? 0);
            $max = (int)($params->getMaxPrice() ?? 999999);

            $priceSql = "SELECT COALESCE(array_cat_agg(variant_ids), '{}'::int4[]) as vids FROM filter_vectors WHERE entity_type = 'price_range' AND entity_id BETWEEN $min AND $max";

            $idx = count($fromClauses);
            $fromClauses[] = "($priceSql) AS g$idx(vids)";
            $intersectChain[] = "g$idx.vids";
        }

        // 4. МЕЖДУ ГРУППАМИ всегда AND (пересечение через &)
        $chain = implode(' & ', $intersectChain);
        $sql = "SELECT (COALESCE($chain, '{}'::int4[]))::text as matched FROM " . implode(', ', $fromClauses);

        $result = DB::selectOne($sql);
        $raw = $result->matched ?? '{}';

        if ($raw === '{}' || $raw === 'null' || empty($raw)) {
            return [];
        }

        return explode(',', trim($raw, '{}'));
    }
}
