<?php

namespace App\Services\Catalog;

use App\Models\Color;
use App\Models\Size;
use App\Models\Gender;
use Illuminate\Support\Facades\DB;

class FilterService
{
    /**
     * Карта соответствия ключей из URL системным ID в индексе
     */
    protected const SYSTEM_MAP = [
        'color'  => Color::SMART_FILTER_ID,  // 1001
        'size'   => Size::SMART_FILTER_ID,   // 1002
        'gender' => Gender::SMART_FILTER_ID, // 1003
    ];

    public function getFilteredProductIds(array $requestFilters): array
    {
        // Базовый запрос: только активные и в наличии
        $query = DB::table('smart_filter_index')
            ->where('is_active', true)
            ->where('stock', '>', 0);

        // 1. Фильтр по категории (всегда по ID)
        if (!empty($requestFilters['category_id'])) {
            $query->where('category_id', $requestFilters['category_id']);
        }

        // 2. Фильтр по бренду (может быть массив ID)
        if (!empty($requestFilters['brands'])) {
            $query->whereIn('brand_id', (array)$requestFilters['brands']);
        }

        // 3. Динамические фильтры (Цвет, Размер, Материал и т.д.)
        // Ожидаем формат: ['color' => [1,2], 'size' => [5], '15' => [101]]
        // Где '15' — это ID из таблицы properties
        if (!empty($requestFilters['attrs'])) {
            foreach ($requestFilters['attrs'] as $key => $values) {
                $propId = self::SYSTEM_MAP[$key] ?? (int)$key;
                $values = (array)$values;

                // Используем whereExists или подзапрос для пересечения множеств
                $query->whereIn('product_variant_id', function ($sub) use ($propId, $values) {
                    $sub->select('product_variant_id')
                        ->from('smart_filter_index')
                        ->where('property_id', $propId)
                        ->whereIn('property_value_id', $values);
                });
            }
        }

        // 4. Фильтр по цене
        if (!empty($requestFilters['price_min'])) {
            $query->where('price', '>=', $requestFilters['price_min']);
        }
        if (!empty($requestFilters['price_max'])) {
            $query->where('price', '<=', $requestFilters['price_max']);
        }

        // Возвращаем только уникальные ID товаров
        return $query->distinct()->pluck('product_id')->toArray();
    }
}

