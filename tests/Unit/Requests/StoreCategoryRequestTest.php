<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreCategoryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator as ValidationValidator;


class StoreCategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Быстрая проверка: проходит валидация или нет.
     */
    private function validate(array $data): bool
    {
        return $this->getValidator($data)->passes();
    }

    /**
     * Создание экземпляра валидатора с правилами из StoreCategoryRequest.
     */
    private function getValidator(array $data): ValidationValidator
    {
        $request = new StoreCategoryRequest();

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    /**
     * Тест: Успешная валидация при корректных данных.
     */
    public function test_validation_passes_with_valid_data(): void
    {
        $data = [
            'name' => 'Новая категория',
            'parent_id' => null,
        ];

        $this->assertTrue($this->validate($data));
    }

    /**
     * Тест: Ошибка, если имя категории уже занято.
     */
    public function test_validation_passes_if_name_is_not_unique(): void
    {
        Category::create([
            'name' => 'Обувь',
        ]);

        $data = [
            'name' => 'Обувь',
        ];

        $validator = $this->getValidator($data);
        $this->assertTrue($validator->passes());
    }

    /**
     * Тест: Ошибка, если родительская категория содержит товары.
     */
    public function test_validation_fails_if_parent_category_has_products(): void
    {
        $parent = Category::create([
            'name' => 'Родитель',
        ]);

        Product::create([
            'name' => 'Тестовый товар',
            'category_id' => $parent->id
        ]);

        $data = [
            'name' => 'Подкатегория',
            'parent_id' => $parent->id
        ];

        $validator = $this->getValidator($data);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString(
            'Выбранная родительская категория содержит товары',
            $validator->errors()->first('parent_id')
        );
    }

    /**
     * Тест: Успешно, если у родительской категории нет товаров.
     */
    public function test_validation_passes_if_parent_category_is_empty(): void
    {
        $parent = Category::create(['name' => 'Пустой родитель', 'slug' => 'empty']);

        $data = [
            'name' => 'Подкатегория',
            'parent_id' => $parent->id
        ];

        $this->assertTrue($this->validate($data));
    }

    /**
     * Тест: Ошибка, если родительской категории не существует.
     */
    public function test_validation_fails_if_parent_id_does_not_exist(): void
    {
        $data = [
            'name' => 'Категория',
            'parent_id' => 999
        ];

        $validator = $this->getValidator($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('parent_id', $validator->errors()->messages());
    }
}
