<?php

namespace App\Repositories\CatalogRepository;

use App\Services\Catalog\DTO\CategoryDataDto;

interface CatalogRepositoryInterface
{
    /**
     * @return array|null
     */
    public function getCategoryData(int $categoryId): ?CategoryDataDto;
    /** Получить ключи фильтров, доступных для категории */
    public function getAllowedFilterKeys(int $categoryId): array;

    /** Получить вектор ID вариантов для конкретной категории */
    public function getCategoryVector(int $categoryId): string;

    /** Получить структуру всех существующих фильтров (бренды, цвета и т.д.) */
    public function getFilterDefinitions(): array;

    /** Выполнить тяжелый агрегирующий запрос (товары + счетчики + цены) */
    public function getAggregatedCatalogData(
        int $categoryId,
        string $groupKeysRaw,
        string $groupVectorsRaw,
        string $categoryVector,
        string $variantIdsRaw,
        int $limit,
        int $offset
    ): object;
}
