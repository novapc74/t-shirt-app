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
            'title' => [
                'required',
                'string',
                'min:2',
                'max:100',
                // Уникальность названия.
                // ignore($this->route('warehouse')) позволяет обновлять склад, не меняя его имя.
                Rule::unique('warehouses', 'title')->ignore($this->route('warehouse')),
            ],
        ];
    }

    /**
     * Подготовка данных перед валидацией.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge([
                // Убираем лишние пробелы по краям и двойные пробелы внутри
                'title' => preg_replace('/\s+/', ' ', trim($this->title)),
            ]);
        }
    }

    /**
     * Кастомные сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Название склада обязательно для заполнения.',
            'title.min'      => 'Название склада должно содержать минимум :min символа.',
            'title.unique'   => 'Склад с таким названием уже существует.',
            'title.max'      => 'Название склада не должно превышать :max символов.',
        ];
    }

    /**
     * Понятные названия атрибутов для стандартных ошибок.
     */
    public function attributes(): array
    {
        return [
            'title' => 'название склада',
        ];
    }
}
