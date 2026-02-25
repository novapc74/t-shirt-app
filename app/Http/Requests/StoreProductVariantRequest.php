<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'sku'        => ['required', 'string', 'max:50', 'unique:product_variants,sku'],
            'properties'         => ['required', 'array', 'min:1'],
            'properties.*.id'    => ['required', 'exists:properties,id'],
            'properties.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Используем стандартный механизм Laravel для красивых имен ошибок
     */
    public function attributes(): array
    {
        $baseAttributes = [
            'product_id' => 'товар',
            'sku'        => 'артикул',
            'properties' => 'характеристики',
        ];

        $dynamicAttributes = [];
        $inputProperties = $this->input('properties', []);

        if (is_array($inputProperties)) {
            // Чтобы в ошибке вместо "properties.0.value" было "Значение (Цвет)"
            foreach ($inputProperties as $index => $item) {
                $propertyId = $item['id'] ?? null;
                $label = 'параметра';

                if ($propertyId) {
                    // Кэшируем названия свойств на время запроса, чтобы не делать 100 SQL слипов
                    $property = Property::find($propertyId);
                    $label = $property ? $property->name : $label;
                }

                $dynamicAttributes["properties.$index.value"] = "значение ($label)";
                $dynamicAttributes["properties.$index.id"]    = "ID характеристики";
            }
        }

        return array_merge($baseAttributes, $dynamicAttributes);
    }

    public function messages(): array
    {
        return [
            'properties.*.id.exists' => 'Указанная характеристика не существует в системе.',
            'properties.*.value.required' => 'Вы не указали :attribute.',
            'sku.unique' => 'Вариант товара с таким артикулом уже существует.',
        ];
    }
}
