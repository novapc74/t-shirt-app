<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Разрешаем всем авторизованным пользователям (или по вашей логике прав)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parent = Category::find($value);

                        if ($parent && $parent->products()->exists()) {
                            $fail('Выбранная родительская категория содержит товары. В нашей системе товары могут находиться только в "листьях" (категориях без подкатегорий).');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Понятные сообщения об ошибках
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название категории.',
            'name.max'      => 'Название слишком длинное (максимум 255 символов).',
            'parent_id.exists' => 'Выбранная родительская категория не найдена в базе данных.',
            'parent_id.integer' => 'Идентификатор родительской категории должен быть числом.',
        ];
    }

    /**
     * Красивые названия полей для стандартных ошибок Laravel
     */
    public function attributes(): array
    {
        return [
            'name'      => 'название категории',
            'parent_id' => 'родительская категория',
        ];
    }
}
