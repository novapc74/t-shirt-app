<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    $category = Category::find($value);
                    if ($category && $category->children()->exists()) {
                        $fail('Нельзя добавить товар в категорию, у которой есть подкатегории.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Необходимо выбрать категорию.',
            'category_id.exists'   => 'Выбранная категория не существует.',
            'name.required'        => 'Название товара обязательно.',
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'категория',
            'name'        => 'название товара',
            'description' => 'описание',
        ];
    }
}

