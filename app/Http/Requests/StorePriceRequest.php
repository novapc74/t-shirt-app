<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'price_type_id' => ['required', 'exists:price_types,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * Преобразование входных данных перед валидацией
     */
    protected function prepareForValidation(): void
    {
        // Если сумма пришла с запятой вместо точки, исправляем это
        if ($this->has('amount')) {
            $this->merge([
                'amount' => str_replace(',', '.', $this->amount),
                'currency' => strtoupper($this->currency), // Приводим валюту к верхнему регистру
            ]);
        }
    }

    public function attributes(): array
    {
        return [
            'product_variant_id' => 'вариант товара',
            'price_type_id' => 'тип цены',
            'amount' => 'сумма',
            'currency' => 'валюта',
        ];
    }
}
