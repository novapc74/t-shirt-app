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

    public function getMatchedVariantIds(CatalogFilterRequestDto $params, ?string $excludeType = null): array
    {
        $filters = collect($params->getFilters())
            ->except([$excludeType, 'price'])
            ->filter()
            ->toArray();

        $priceRange = [];
        if ($params->hasPriceFilter() && $excludeType !== 'price') {
            $priceRange = [
                'min' => (int)($params->getMinPrice() ?? 0),
                'max' => (int)($params->getMaxPrice() ?? 100000000),
            ];
        }

        return $this->filterRepository->findMatchedVariantIds($filters, $priceRange);
    }
}
