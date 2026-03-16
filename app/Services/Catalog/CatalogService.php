<?php

namespace App\Services\Catalog;

use App\Http\Requests\CatalogFilterRequest;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use App\Repositories\CatalogRepository\CatalogRepositoryInterface;

class CatalogService
{
    private const int PER_PAGE = 12;

    public function __construct(
        private readonly CatalogRepositoryInterface $catalogRepository,
        private readonly FilterService $filterService
    ) {}

    /**
     * Получить данные категории с примененными фильтрами и умными счетчиками.
     */
    public function getCategoryCatalog(int $categoryId, CatalogFilterRequest $request): array
    {
        if(!$categoryDataDto = $this->catalogRepository->getCategoryData($categoryId)) {
            #TODO return empty response...
            return [];
        }

        // 1. Получаем доступные ключи фильтров для этой категории (из репозитория)
        $allowedKeys = $this->catalogRepository->getAllowedFilterKeys($categoryDataDto->id);

        $dto = CatalogFilterRequestDto::fromRequest($request, $allowedKeys);

        // 2. Итоговые ID вариантов для списка товаров (логика AND)
        $variantIds = $this->filterService->getMatchedVariantIds($dto);
        $vIdsRaw = '{' . implode(',', $variantIds ?: []) . '}';

        // 3. Подготовка данных для "умных счетчиков" (логика OR внутри группы)
        $groupKeys = [];
        $groupVectors = [];
        foreach ($allowedKeys as $type) {
            $groupKeys[] = $type;
            $baseIds = $this->filterService->getMatchedVariantIds($dto, $type);
            $groupVectors[] = '{' . implode(',', $baseIds ?: []) . '}';
        }

        // Форматируем массивы для Postgres (unnest)
        $groupKeysRaw = '{' . implode(',', $groupKeys) . '}';
        $groupVectorsRaw = '{' . implode(',', array_map(fn($v) => '"' . $v . '"', $groupVectors)) . '}';

        $page = $dto->getPage();

        // 4. Запрашиваем агрегированные данные из репозитория одним запросом
        $dbResult = $this->catalogRepository->getAggregatedCatalogData(
            $categoryId,
            $groupKeysRaw,
            $groupVectorsRaw,
            $this->catalogRepository->getCategoryVector($categoryId),
            $vIdsRaw,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE
        );

        // 5. Декодирование JSON и сборка финального ответа
        $productsData = json_decode($dbResult->products_json, true);
        $counters = $this->mapCounters(json_decode($dbResult->counters_json, true));
        $priceStats = json_decode($dbResult->price_stats, true);

        return [
            'category' => $categoryDataDto->title,
            'products' => [
                'data' => $productsData['items'] ?? [],
                'meta' => [
                    'total'        => (int)($productsData['total'] ?? 0),
                    'current_page' => $page,
                    'last_page'    => (int)ceil(($productsData['total'] ?? 0) / self::PER_PAGE),
                    'per_page'     => self::PER_PAGE,
                ]
            ],
            'filters' => $this->assembleFilters($counters, $dto, $priceStats)
        ];
    }

    /**
     * Сборка структуры фильтров с учетом счетчиков и активных состояний.
     */
    private function assembleFilters(array $counters, CatalogFilterRequestDto $dto, array $priceStats): array
    {
        $definitions = $this->catalogRepository->getFilterDefinitions();

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
                $val = (array)$val;
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

    /**
     * Преобразование плоского массива счетчиков в ассоциативный [type][id] => total.
     */
    private function mapCounters(?array $raw): array
    {
        $res = [];
        foreach ($raw ?? [] as $item) {
            $res[$item['entity_type']][$item['entity_id']] = $item['total'];
        }
        return $res;
    }
}
