<?php

namespace Tests\Unit\Requests;

use PHPUnit\Framework\Attributes\DataProvider;
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
        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    /**
     * Успешная валидация корректных данных.
     */
    public function test_validation_passes_with_valid_data(): void
    {
        $data = [
            'title' => 'Новая категория',
            'parent_id' => null,
            'priority' => 50
        ];

        $this->assertTrue($this->getValidator($data)->passes());
    }

    /**
     * Ошибка, если название категории уже занято (Unique).
     */
    public function test_validation_fails_if_title_is_not_unique(): void
    {
        Category::create(['title' => 'Обувь', 'slug' => 'obuv']);

        $validator = $this->getValidator(['title' => 'Обувь']);

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'Категория с таким именем уже существует.',
            $validator->errors()->first('title')
        );
    }

    /**
     * Ошибка, если родительская категория содержит товары.
     */
    public function test_validation_fails_if_parent_category_has_products(): void
    {
        $parent = Category::create(['title' => 'Родитель', 'slug' => 'parent']);

        // Создаем товар в этой категории
        Product::create([
            'title' => 'Тестовый товар',
            'slug' => 'test-product',
            'category_id' => $parent->id
        ]);

        $validator = $this->getValidator([
            'title' => 'Подкатегория',
            'parent_id' => $parent->id
        ]);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('содержит товары', $validator->errors()->first('parent_id'));
    }

    /**
     * Ошибка при неверном приоритете.
     */
    #[DataProvider('invalidPriorityProvider')]
    public function test_validation_fails_for_invalid_priority($priority, $expectedMessage): void
    {
        $validator = $this->getValidator([
            'title' => 'Тест',
            'priority' => $priority
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals($expectedMessage, $validator->errors()->first('priority'));
    }

    public static function invalidPriorityProvider(): array
    {
        return [
            'слишком маленький' => [0, 'Приоритет категории должен быть от 1 до 100'],
            'слишком большой'   => [101, 'Приоритет категории должен быть от 1 до 100'],
            'не число'          => ['abc', 'Приоритет категории должен быть целым числом'],
        ];
    }

    /**
     * Ошибка, если родительской категории не существует.
     */
    public function test_validation_fails_if_parent_id_does_not_exist(): void
    {
        $validator = $this->getValidator([
            'title' => 'Категория',
            'parent_id' => 999
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'Выбранная родительская категория не найдена в базе данных.',
            $validator->errors()->first('parent_id')
        );
    }

    /**
     * Ошибка при пустом заголовке или слишком длинном.
     */
    public function test_validation_fails_for_invalid_title(): void
    {
        // Проверка required
        $this->assertFalse($this->getValidator(['title' => ''])->passes());

        // Проверка max:255
        $this->assertFalse($this->getValidator(['title' => str_repeat('a', 256)])->passes());
    }
}
