<?php

namespace App\Services\Catalog\DTO;

use App\Http\Requests\CatalogFilterRequest;

class CatalogFilterRequestDto
{
    public function __construct(
        private readonly array $filters = [],
        private readonly int $page = 1,
    ) {
    }


    public static function fromRequest(CatalogFilterRequest $request, array $allowedKeys = []): self
    {
        $data = $request->validated();
        $inputFilters = $data['filters'] ?? [];

        // 1. Создаем маску разрешенных ключей
        $allowedMask = array_fill_keys($allowedKeys, true);
        $allowedMask['price'] = true;

        // 2. Оставляем только разрешенные фильтры
        $filtered = array_intersect_key($inputFilters, $allowedMask);

        // 3. Берем страницу из корня валидированных данных, а не из отфильтрованных ключей
        $page = isset($data['page']) ? (int)$data['page'] : 1;

        return new self($filtered, $page);
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
