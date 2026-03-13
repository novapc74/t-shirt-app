<?php

namespace App\Services\Catalog;

use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use Illuminate\Support\Facades\DB;

class FilterService
{
    /**
     * Возвращает массив ID вариантов, прошедших фильтры.
     *
     * @param string|null $excludeType Группа, которую нужно исключить (для умных счетчиков)
     */
    public function getMatchedVariantIds(CatalogFilterRequestDto $params, ?string $excludeType = null): array
    {
        $filters = $params->getFilters();
        $selectParts = [];

        // 1. Базовый слой — наличие (entity_id = 1 для системы)
        $selectParts['stock'] = "SELECT variant_ids FROM filter_vectors WHERE entity_type = 'system' AND entity_id = 1";

        // 2. Группы фильтров
        foreach ($filters as $type => $ids) {
            if ($type === $excludeType || empty($ids) || $type === 'price') {
                continue;
            }

            $idsList = implode(',', array_map('intval', (array)$ids));
            // Используем агрегат array_cat_agg для OR внутри группы
            $selectParts[$type] = "SELECT COALESCE(array_cat_agg(variant_ids), '{}'::int4[])
                               FROM filter_vectors
                               WHERE entity_type = '$type' AND entity_id IN ($idsList)";
        }

        // 3. Цена
        if ($params->hasPriceFilter() && $excludeType !== 'price') {
            $min = (int)$params->getMinPrice();
            $max = (int)$params->getMaxPrice();

            $selectParts['price'] = "SELECT COALESCE(array_cat_agg(variant_ids), '{}'::int4[])
                                 FROM filter_vectors
                                 WHERE entity_type = 'price_range'
                                   AND entity_id BETWEEN $min AND $max";
        }

        // Собираем итоговое пересечение (AND между группами)
        // Оборачиваем каждый подзапрос в скобки
        $intersectChain = array_map(fn($sql) => "($sql)", array_values($selectParts));

        // Используем оператор & для пересечения всех полученных векторов
        $finalSql = "SELECT (".implode(' & ', $intersectChain).") as matched";

        $result = DB::selectOne($finalSql);

        if (!$result || empty($result->matched)) {
            return [];
        }

        // Преобразуем строку Postgres {1,2,3} в массив PHP [1,2,3]
        return str_getcsv(trim($result->matched, '{}'));
    }
}
