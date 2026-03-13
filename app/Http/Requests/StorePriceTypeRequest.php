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
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'unique:price_types,title'],
            'priority' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название типа цены',
            'priority' => 'приоритет',
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Пожалуйста, укажите название типа цены',
            'title.string' => 'Название должно быть текстовой строкой',
            'title.max' => 'Название слишком длинное (максимум 255 символов)',
            'title.unique' => 'Тип цены с таким названием уже существует',
            'priority.between' => 'Значение приоритета должно быть в интервале от 1 до 100',
            'priority.integer' => 'Тип приоритета должно быть целым числом',
        ];
    }
}
