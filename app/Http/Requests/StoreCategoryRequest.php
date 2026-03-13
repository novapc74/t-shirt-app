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
            'title' => ['required', 'unique:categories,title', 'string', 'max:255'],
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
            'priority' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * Понятные сообщения об ошибках
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название категории.',
            'title.max'      => 'Название слишком длинное (максимум 255 символов).',
            'title.unique' => 'Категория с таким именем уже существует.',
            'parent_id.exists' => 'Выбранная родительская категория не найдена в базе данных.',
            'parent_id.integer' => 'Идентификатор родительской категории должен быть числом.',
            'priority.integer' => 'Приоритет категории должен быть целым числом',
            'priority.between' => 'Приоритет категории должен быть от 1 до 100',
        ];
    }

    /**
     * Красивые названия полей для стандартных ошибок Laravel
     */
    public function attributes(): array
    {
        return [
            'title'      => 'название категории',
            'parent_id' => 'родительская категория',
        ];
    }
}
