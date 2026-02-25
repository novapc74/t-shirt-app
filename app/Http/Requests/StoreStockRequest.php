<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockRequest extends FormRequest
{
    /**
     * Разрешить выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации.
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'exists:product_variants,id',
            ],
            'warehouse_id' => [
                'required',
                'exists:warehouses,id',
                Rule::unique('stocks')->where(function ($query) {
                    // $this внутри замыкания в FormRequest содержит все входные данные
                    return $query->where('product_variant_id', $this->product_variant_id)
                        ->where('warehouse_id', $this->warehouse_id);
                })->ignore($this->route('stock')),
            ],
            'quantity' => [
                'required',
                'integer',
                'min:0', // Остаток не может быть меньше нуля
                'max:1000000', // Разумный предел для валидации
            ],
        ];
    }

    /**
     * Кастомные сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            'product_variant_id.required' => 'Необходимо выбрать вариант товара.',
            'product_variant_id.exists'   => 'Выбранный вариант товара не существует.',
            'warehouse_id.required'       => 'Укажите склад.',
            'warehouse_id.exists'         => 'Выбранный склад не существует.',
            'warehouse_id.unique'         => 'Для этого товара на данном складе уже заведена запись остатков.',
            'quantity.required'           => 'Введите количество.',
            'quantity.integer'            => 'Количество должно быть целым числом.',
            'quantity.min'                => 'Количество не может быть отрицательным.',
        ];
    }

    /**
     * Понятные названия атрибутов.
     */
    public function attributes(): array
    {
        return [
            'product_variant_id' => 'вариант товара',
            'warehouse_id'       => 'склад',
            'quantity'           => 'количество',
        ];
    }
}
