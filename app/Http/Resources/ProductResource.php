<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $category
 * @property mixed $variants
 */
class ProductResource extends JsonResource
{

    // app/Http/Resources/ProductResource.php
    public function toArray($request): array
    {
        $minPrice = (float) $this->variants->min(function($v) {
            return $v->prices->first()?->price;
        });

        return [
            'id' => $this->id,
            'name' => $this->title,
            'slug' => $this->slug,
            'category_name' => $this->category?->title,
            'min_price' => $minPrice > 0 ? $minPrice : null,
            'variants' => $this->variants->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (float)$v->prices->first()?->price,
                'stock' => (int)$v->stocks->sum('quantity'),
                'attributes' => [
                    'color'  => (int)$v->color_id,
                    'size'   => (int)$v->size_id,
                    'gender' => (int)$v->gender_id,
                ],
                'attribute_names' => [
                    'color'  => $v->color?->title,
                    'size'   => $v->size?->title,
                    'gender' => $v->gender?->title,
                ]
            ]),
        ];
    }

}
