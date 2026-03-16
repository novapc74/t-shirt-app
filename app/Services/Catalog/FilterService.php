<?php

namespace App\Services\Catalog;

use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use App\Repositories\CatalogRepository\FilterRepositoryInterface;

class FilterService
{
    public function __construct(
        private readonly FilterRepositoryInterface $filterRepository,
    )
    {
    }

    public function getMatchedVariantIds(CatalogFilterRequestDto $dto, ?string $excludeType = null): array
    {
        $filters = collect($dto->getFilters())
            ->except([$excludeType, 'price'])
            ->filter()
            ->toArray();

        $priceRange = [];

        if ($dto->hasPriceFilter() && $excludeType !== 'price') {
            $priceRange = [
                'min' => (int)($dto->getMinPrice() ?? 0),
                'max' => (int)($dto->getMaxPrice() ?? 100000000),
            ];
        }

        return $this->filterRepository->findMatchedVariantIds($filters, $priceRange);
    }
}
