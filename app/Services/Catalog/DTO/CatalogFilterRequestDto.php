<?php

namespace App\Services\Catalog\DTO;

use App\Http\Requests\CatalogFilterRequest;

class CatalogFilterRequestDto
{
    private bool $isOrStrategy = true;

    public function __construct(private readonly array $filters = [], private readonly int $page = 1)
    {
        $filterGroupCount = 0;
        foreach ($this->filters as $key => $values) {
            if ($key === 'price' || empty($values)) continue;
            $filterGroupCount++;
        }
        // Стратегия OR (объединение), если выбрана только ОДНА категория (например, только Цвет)
        $this->isOrStrategy = ($filterGroupCount <= 1);
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

    public function isOrStrategy(): bool
    {
        return $this->isOrStrategy;
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
