<?php

namespace App\Services\Catalog;

use App\Models\{Color, Size, Gender, Property, PropertyValue, Brand};
use App\Services\Catalog\DTO\ProductFilterParams;
use Illuminate\Support\Facades\DB;

class FilterService
{
    protected const SYSTEM_MAP = [
        'color' => Color::SMART_FILTER_ID,
        'size' => Size::SMART_FILTER_ID,
        'gender' => Gender::SMART_FILTER_ID,
    ];

    /**
     * Поиск ID товаров по индексу
     */
    public function getFilteredProductIds(ProductFilterParams $params, int $categoryId): array
    {
        $query = DB::table('smart_filter_index')
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('stock', '>', 0);

        // Фильтр по брендам
        if (!empty($params->brands)) {
            $query->whereIn('brand_id', $params->brands);
        }

        // Фильтр по цене
        if ($params->minPrice) $query->where('price', '>=', $params->minPrice);
        if ($params->maxPrice) $query->where('price', '<=', $params->maxPrice);

        // Динамические атрибуты (Пересечение множеств)
        if (!empty($params->filters)) {
            foreach ($params->filters as $key => $values) {
                $propId = self::SYSTEM_MAP[$key] ?? (int)$key;
                $values = (array)$values;

                $query->whereIn('product_variant_id', function ($sub) use ($propId, $values) {
                    $sub->select('product_variant_id')
                        ->from('smart_filter_index')
                        ->where('property_id', $propId)
                        ->whereIn('property_value_id', $values)
                        ->where('stock', '>', 0);
                });
            }
        }

        return $query->distinct()->pluck('product_id')->toArray();
    }

    /**
     * Сбор доступных опций фильтрации (цвета, размеры и т.д.) с количеством товаров
     */
    public function getAggregatedAttributes(int $categoryId, array $productIds, ProductFilterParams $params): array
    {
        // 1. Скелет всех опций
        $allOptions = DB::table('smart_filter_index')
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->select('property_id', 'property_value_id')
            ->distinct()
            ->get()
            ->groupBy('property_id');

        // 2. Текущие счетчики (по товарам)
        $currentCounts = DB::table('smart_filter_index')
            ->whereIn('product_id', $productIds)
            ->select('property_id', 'property_value_id', DB::raw('count(distinct product_id) as total'))
            ->groupBy('property_id', 'property_value_id')
            ->get()
            ->keyBy(fn($row) => $row->property_id . '_' . $row->property_value_id);

        return $allOptions->map(function ($items, $propId) use ($categoryId, $params, $currentCounts) {
            $propId = (int)$propId;
            $paramsCopy = clone $params;
            $propKey = $this->getPropKeyById($propId);

            $filters = $paramsCopy->filters;
            unset($filters[$propKey], $filters[(string)$propId], $filters[(int)$propId]);
            $paramsCopy->filters = $filters;

            // --- ИЗМЕНЕНИЕ ЗДЕСЬ ---
            // Получаем ID ВАРИАНТОВ, которые подходят под остальные фильтры
            $variantIdsForFacet = $this->getFilteredVariantIds($paramsCopy, $categoryId);

            // Теперь смотрим, какие значения свойств есть именно у этих ВАРИАНТОВ
            $availableValueIds = DB::table('smart_filter_index')
                ->whereIn('product_variant_id', $variantIdsForFacet)
                ->where('property_id', $propId)
                ->where('stock', '>', 0)
                ->distinct()
                ->pluck('property_value_id')
                ->map(fn($id) => (int)$id)
                ->toArray();

            $valueIds = $items->pluck('property_value_id')->unique();
            $names = $this->loadNamesForProperty($propId, $valueIds);

            return [
                'id'    => $propId,
                'title' => $this->resolvePropertyTitle($propId),
                'items' => $items->map(function($row) use ($currentCounts, $availableValueIds, $names, $propId) {
                    $vId = (int)$row->property_value_id;
                    $isDisabled = !in_array($vId, $availableValueIds);

                    return [
                        'id'       => $vId,
                        'count'    => (int)($currentCounts[$propId . '_' . $vId]->total ?? 0),
                        'title'    => $names[$vId] ?? 'Unknown',
                        'disabled' => $isDisabled,
                    ];
                })
                    ->filter(fn($i) => $i['title'] !== 'Unknown')
                    ->values()
            ];
        })->values()->toArray();
    }

    /**
     * Новый вспомогательный метод для получения ID вариантов
     */
    private function getFilteredVariantIds(ProductFilterParams $params, int $categoryId): array
    {
        $query = DB::table('smart_filter_index')
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('stock', '>', 0);

        if (!empty($params->brands)) {
            $query->whereIn('brand_id', $params->brands);
        }

        if ($params->minPrice) $query->where('price', '>=', $params->minPrice);
        if ($params->maxPrice) $query->where('price', '<=', $params->maxPrice);

        if (!empty($params->filters)) {
            foreach ($params->filters as $key => $values) {
                $pId = self::SYSTEM_MAP[$key] ?? (int)$key;
                $values = (array)$values;

                // Важное уточнение: сужаем выборку вариантов по каждому фильтру
                $query->whereIn('product_variant_id', function ($sub) use ($pId, $values) {
                    $sub->select('product_variant_id')
                        ->from('smart_filter_index')
                        ->where('property_id', $pId)
                        ->whereIn('property_value_id', $values)
                        ->where('stock', '>', 0);
                });
            }
        }

        return $query->distinct()->pluck('product_variant_id')->toArray();
    }


    /**
     * Вспомогательный метод для определения ключа фильтра
     */
    private function getPropKeyById(int $propId): string
    {
        $map = array_flip(self::SYSTEM_MAP);
        return $map[$propId] ?? (string)$propId;
    }



    private function loadNamesForProperty(int $propId, $valueIds): array
    {
        return match($id = (int)$propId) {
            \App\Models\Color::SMART_FILTER_ID  => \App\Models\Color::whereIn('id', $valueIds)->pluck('title', 'id')->toArray(),
            \App\Models\Size::SMART_FILTER_ID   => \App\Models\Size::whereIn('id', $valueIds)->pluck('title', 'id')->toArray(),
            \App\Models\Gender::SMART_FILTER_ID => \App\Models\Gender::whereIn('id', $valueIds)->pluck('title', 'id')->toArray(),
            default => \App\Models\PropertyValue::whereIn('id', $valueIds)->where('property_id', $id)->pluck('value', 'id')->toArray(),
        };
    }


    public function getPriceRange(int $categoryId): array
    {
        $data = DB::table('smart_filter_index')
            ->where('category_id', $categoryId)
            ->selectRaw('MIN(price) as min, MAX(price) as max')
            ->first();

        return ['min' => (float)($data->min ?? 0), 'max' => (float)($data->max ?? 0)];
    }

    public function getAvailableBrands(int $categoryId, array $productIds): array
    {
        return Brand::whereIn('id', function ($q) use ($categoryId, $productIds) {
            $q->select('brand_id')->from('smart_filter_index')
                ->where('category_id', $categoryId)
                ->whereIn('product_id', $productIds);
        })->get(['id', 'title', 'slug'])->toArray();
    }

    private function resolvePropertyTitle(int $id): string
    {
        return match ($id) {
            Color::SMART_FILTER_ID => 'Цвет',
            Size::SMART_FILTER_ID => 'Размер',
            Gender::SMART_FILTER_ID => 'Пол',
            default => Property::find($id)?->title ?? 'Свойство'
        };
    }

    private function loadAttributeNames($ids): array
    {
        // Собираем имена из разных таблиц (можно закешировать)
        $pValues = PropertyValue::whereIn('id', $ids)->pluck('value', 'id');
        $colors = Color::whereIn('id', $ids)->pluck('title', 'id');
        $sizes = Size::whereIn('id', $ids)->pluck('title', 'id');
        $genders = Gender::whereIn('id', $ids)->pluck('title', 'id');

        return ($pValues->toArray() + $colors->toArray() + $sizes->toArray() + $genders->toArray());
    }
}

