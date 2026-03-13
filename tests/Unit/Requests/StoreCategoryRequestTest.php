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

    private function getValidator(array $data): ValidationValidator
    {
        $request = new StoreCategoryRequest();

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }

    /**
     * Тест: Успешная валидация при корректных данных.
     */
    public function test_validation_passes_with_valid_data(): void
    {
        $this->assertTrue(
            $this->getValidator([
                'title' => 'Электроника',
                'parent_id' => null,
            ])->passes()
        );
    }

    /**
     * Тест: Ошибка при пустом заголовке.
     */
    public function test_validation_fails_if_title_is_missing(): void
    {
        $validator = $this->getValidator(['title' => '']);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Укажите название категории.', $validator->errors()->first('title'));
    }

    /**
     * Тест: Ошибка, если родительская категория содержит товары.
     * (Проверка логики "товары только в листьях")
     */
    public function test_validation_fails_if_parent_category_has_products(): void
    {
        $parent = Category::create(['title' => 'Одежда', 'slug' => 'odezhda']);

        // Создаем товар в этой категории
        Product::create([
            'title' => 'Футболка', // Убедитесь, что в БД 'title', а не 'name'
            'slug' => 't-shirt',
            'category_id' => $parent->id,
        ]);

        $validator = $this->getValidator([
            'title' => 'Мужская одежда',
            'parent_id' => $parent->id,
        ]);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString(
            'содержит товары',
            $validator->errors()->first('parent_id')
        );
    }

    /**
     * Тест: Успешно, если у родительской категории нет товаров.
     */
    public function test_validation_passes_if_parent_category_is_empty(): void
    {
        $parent = Category::create(['title' => 'Пустой родитель', 'slug' => 'empty']);

        $this->assertTrue(
            $this->getValidator([
                'title' => 'Подкатегория',
                'parent_id' => $parent->id,
            ])->passes()
        );
    }

    /**
     * Тест: Ошибка, если родительской категории не существует.
     */
    public function test_validation_fails_if_parent_id_does_not_exist(): void
    {
        $validator = $this->getValidator([
            'title' => 'Тест',
            'parent_id' => 999,
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'Выбранная родительская категория не найдена в базе данных.',
            $validator->errors()->first('parent_id')
        );
    }

    /**
     * Тест: Ошибка, если название слишком длинное.
     */
    public function test_validation_fails_if_title_is_too_long(): void
    {
        $validator = $this->getValidator(['title' => str_repeat('a', 256)]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Название слишком длинное (максимум 255 символов).', $validator->errors()->first('title'));
    }

    /**
     * Тест: Ошибка, если название категории уже занято.
     */
    public function test_validation_fails_if_title_is_not_unique(): void
    {
        // 1. Создаем существующую категорию
        Category::create([
            'title' => 'Обувь',
            'slug' => 'obuv',
        ]);

        // 2. Пытаемся валидировать такое же название
        $validator = $this->getValidator([
            'title' => 'Обувь',
        ]);

        $this->assertFalse($validator->passes(), 'Валидация должна была упасть из-за неуникального заголовка');
        $this->assertArrayHasKey('title', $validator->errors()->messages());
        $this->assertEquals('Категория с таким именем уже существует.', $validator->errors()->first('title'));
    }
}
