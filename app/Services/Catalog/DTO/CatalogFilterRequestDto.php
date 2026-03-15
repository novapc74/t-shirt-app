<?php

namespace App\Services\Catalog\DTO;

use App\Http\Requests\CatalogFilterRequest;

class CatalogFilterRequestDto
{
    public function __construct(
        private readonly array $filters = [],
        private readonly int $page = 1
    ) {
    }


    public static function fromRequest(CatalogFilterRequest $request, array $allowedKeys = []): self
    {
        $data = $request->validated();

        $filters = $data['filters'] ?? [];
        $currentPage = (int)($data['page'] ?? 1);

        $allowedKeys[] = 'price';

        $filtered = array_filter(
            $filters,
            fn($key) => in_array($key, $allowedKeys),
            ARRAY_FILTER_USE_KEY
        );

        return new self($filtered, $currentPage);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getFilters(string $key = null): array
    {
        if ($key === null) {
            return $this->filters;
        }

        return $this->filters[$key] ?? [];
    }

    public function getMinPrice(): ?string
    {
        return $this->filters['price']['min'] ?? null;
    }

    public function getMaxPrice(): ?string
    {
        return $this->filters['price']['max'] ?? null;
    }

    public function hasPriceFilter(): bool
    {
        return $this->getMaxPrice() || $this->getMinPrice();
    }
}
