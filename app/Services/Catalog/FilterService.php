<?php

namespace App\Services\Catalog;

use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use Illuminate\Support\Facades\DB;

class FilterService
{
    /**
     * Возвращает массив ID вариантов, прошедших фильтры.
     */
    public function getMatchedVariantIds(CatalogFilterRequestDto $params, ?string $excludeType = null): array
    {
        $filters = $params->getFilters();
        $selectParts = [];
        $bindings = [];

        // 1. БАЗОВЫЙ СЛОЙ (Наличие товара в системе)
        $selectParts[] = "SELECT variant_ids FROM filter_vectors WHERE entity_type = 'system' AND entity_id = 1";

        // 2. ГРУППЫ ФИЛЬТРОВ (Бренды, Цвета, Свойства и т.д.)
        foreach ($filters as $type => $ids) {
            if ($type === $excludeType || empty($ids) || $type === 'price') {
                continue;
            }

            $idsList = implode(',', array_map('intval', (array)$ids));

            // Используем подзапрос с COALESCE, чтобы избежать NULL при пересечении (&)
            $selectParts[] = "SELECT COALESCE((
                SELECT array_cat_agg(variant_ids)
                FROM filter_vectors
                WHERE entity_type = '$type' AND entity_id IN ($idsList)
            ), '{}'::int4[])";
        }

        // 3. ФИЛЬТР ПО ЦЕНЕ (Исправленная логика нижней границы)
        if ($params->hasPriceFilter() && $excludeType !== 'price') {
            // Берем значения из DTO. Если одно из них не задано, ставим экстремумы.
            $min = (int)($params->getMinPrice() ?? 0);
            $max = (int)($params->getMaxPrice() ?? 100000000);

            /**
             * КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ:
             * Мы оборачиваем агрегацию цен в COALESCE. Если в базе нет цен в диапазоне,
             * вернется empty array {}, а не NULL. Это не даст "сломать" итоговое пересечение.
             */
            $selectParts[] = "SELECT COALESCE((
                SELECT array_cat_agg(variant_ids)
                FROM filter_vectors
                WHERE entity_type = 'price_range'
                  AND entity_id >= :min_p
                  AND entity_id <= :max_p
            ), '{}'::int4[])";

            $bindings['min_p'] = $min;
            $bindings['max_p'] = $max;
        }

        // 4. СБОРКА ИТОГОВОГО ПЕРЕСЕЧЕНИЯ (AND между группами)
        $intersectChain = array_map(fn($sql) => "($sql)", $selectParts);

        if (empty($intersectChain)) {
            return [];
        }

        // Оператор & выполняет пересечение векторов (AND)
        $finalSql = "SELECT (" . implode(' & ', $intersectChain) . ") as matched";

        $result = DB::selectOne($finalSql, $bindings);

        // Если Postgres вернул NULL или пустой результат
        if (!$result || empty($result->matched) || $result->matched === '{}') {
            return [];
        }

        // Очищаем от фигурных скобок {}
        $trimmed = trim($result->matched, '{}');

        if ($trimmed === '') {
            return [];
        }

        // Преобразуем строку "1,2,3" в массив чисел PHP
        return array_map('intval', explode(',', $trimmed));
    }
}
