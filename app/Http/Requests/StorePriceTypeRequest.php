<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:price_types,name'],
            'slug' => [
                'required',
                'string',
                'max:255',
                // Уникальность слага, исключая текущую запись при обновлении
                Rule::unique('price_types', 'slug')->ignore($this->route('price_type'))
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название типа цены',
            'slug' => 'символьный код (slug)',
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Пожалуйста, укажите название типа цены.',
            'name.string'   => 'Название должно быть текстовой строкой.',
            'name.max'      => 'Название слишком длинное (максимум 255 символов).',
            'name.unique'   => 'Тип цены с таким названием уже существует.',

            'slug.required' => 'Символьный код (slug) обязателен для заполнения.',
            'slug.string'   => 'Слаг должен быть строкой.',
            'slug.unique'   => 'Этот символьный код уже занят другим типом цены.',
            'slug.max'      => 'Слаг не может быть длиннее 255 символов.',
        ];
    }
}
