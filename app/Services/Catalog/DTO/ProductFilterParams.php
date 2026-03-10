<?php

namespace App\Services\Catalog\DTO;

use Illuminate\Http\Request;

class ProductFilterParams
{
    private bool $isOrStrategy = true;

    public function __construct(private readonly array $filters = [], private readonly string $sort = 'newest')
    {
        $i = 0;
        foreach ($this->filters as $values) {
            if (!empty($values)) {
                $i++;
            }
            if ($i > 1) {
                $this->isOrStrategy = false;
                break;
            }
        }
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            (array)$request->input('filters', []),
            (string)$request->input('sort', 'newest')
        );
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

    public function getSort(): string
    {
        return $this->sort;
    }
}
