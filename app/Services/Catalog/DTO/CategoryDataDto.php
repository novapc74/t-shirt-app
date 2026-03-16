<?php

namespace App\Services\Catalog\DTO;

readonly class CategoryDataDto
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?int $parentId,
    ) {
    }

    /**
     * Статический фабричный метод для создания DTO из массива или объекта БД
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            title: (string)$data['title'],
            slug: (string)$data['slug'],
            parentId: isset($data['parent_id']) ? (int)$data['parent_id'] : null,
        );
    }
}
