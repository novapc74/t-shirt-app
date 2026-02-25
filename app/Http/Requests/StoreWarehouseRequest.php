<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения этого запроса.
     */
    public function authorize(): bool
    {
        // В реальном приложении здесь может быть: return $this->user()->can('manage-inventory');
        return true;
    }

    /**
     * Правила валидации.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                // Уникальность названия.
                // ignore($this->route('warehouse')) позволяет обновлять склад, не меняя его имя.
                Rule::unique('warehouses', 'name')->ignore($this->route('warehouse')),
            ],
        ];
    }

    /**
     * Подготовка данных перед валидацией.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                // Убираем лишние пробелы по краям и двойные пробелы внутри
                'name' => preg_replace('/\s+/', ' ', trim($this->name)),
            ]);
        }
    }

    /**
     * Кастомные сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Название склада обязательно для заполнения.',
            'name.min'      => 'Название склада должно содержать минимум :min символа.',
            'name.unique'   => 'Склад с таким названием уже существует.',
            'name.max'      => 'Название склада не должно превышать :max символов.',
        ];
    }

    /**
     * Понятные названия атрибутов для стандартных ошибок.
     */
    public function attributes(): array
    {
        return [
            'name' => 'название склада',
        ];
    }
}
