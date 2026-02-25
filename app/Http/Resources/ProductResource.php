<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $interactiveProps = ['color', 'size', 'gender', 'head_size'];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Добавляем название категории самого товара
            'category_name' => $this->category->name,

            'min_price' => (float)$this->variants->flatMap->prices->min('amount'),

            'variants' => $this->variants->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (float)$v->prices->first()?->amount,
                'stock' => (int)$v->stocks->sum('quantity'),
                'properties' => $v->properties->map(fn($p) => [
                    'group' => $p->property->name,
                    'value' => $p->value,
                    'label' => $p->label,
                    'slug' => $p->property->slug,
                ]),
            ]),

            'grouped_specs' => $this->variants->flatMap->properties
                ->filter(fn($p) => in_array($p->property->slug, $interactiveProps))
                ->map(fn($p) => [
                    'group' => $p->property->name,
                    'slug' => $p->property->slug,
                    'value' => $p->label ?? $p->value,
                    'color_hex' => $p->property->slug === 'color' ? $p->value : null,
                    'priority' => $p->priority
                ])
                ->unique(fn($item) => $item['group'] . $item['value'])
                ->groupBy('group')
                ->map(fn($options) => $options->sortBy('priority')->values()),
        ];
    }
}
