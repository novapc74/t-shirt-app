<?php

namespace App\Services\Catalog\DTO;

use Illuminate\Http\Request;

class ProductFilterParams
{
    public function __construct(
        public array $filters = [],
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public string $sort = 'newest',
        public ?array $productTypes = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            filters: (array)$request->input('filters', []),
            minPrice: $request->input('min_price'),
            maxPrice: $request->input('max_price'),
            sort: $request->input('sort', 'newest'),
            productTypes: (array)$request->input('product_types', []),
        );
    }
}
