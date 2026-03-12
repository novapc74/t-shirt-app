<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('filters.price')) {
            $price = $this->input('filters.price');
            $filters = $this->input('filters', []);

            $min = ($price['min'] ?? null) !== null ? (float)$price['min'] : 0;
            $max = (isset($price['max']) && $price['max'] !== '') ? (float)$price['max'] : null;

            $filters['price'] = [
                'min' => $min,
                'max' => $max,
            ];

            $this->merge(['filters' => $filters]);
        }
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.price.min' => ['nullable', 'numeric', 'min:0'],
            'filters.price.max' => [
                'nullable',
                'numeric',
                'gte:filters.price.min'
            ],
            'filters.*' => ['sometimes', 'array'],
            'filters.*.*' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    if (str_contains($attribute, 'filters.price')) return;
                    if (!is_numeric($value) || $value < 1) {
                        $fail("Значение $attribute должно быть числом больше 0.");
                    }
                }
            ],
        ];
    }


    protected function failedValidation(Validator $validator)
    {
        // Если запрос пришел от Inertia, отдаем управление стандартному механизму (редиректу)
        if ($this->header('X-Inertia')) {
            parent::failedValidation($validator);
        }

        // Для Postman и прочих отдаем JSON
        throw new ValidationException($validator, response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}
