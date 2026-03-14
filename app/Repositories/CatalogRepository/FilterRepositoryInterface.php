<?php

namespace App\Repositories\CatalogRepository;

interface FilterRepositoryInterface
{
    public function findMatchedVariantIds(array $filters, array $priceRange): array;
}

