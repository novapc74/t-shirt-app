<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StoreProductRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Вспомогательный метод для запуска валидации.
     */
    protected function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreProductRequest();

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_data(): void
    {
        $category = Category::factory()->create();

        $validator = $this->validate([
            'category_id' => $category->id,
            'name'        => 'Футболка с принтом',
            'description' => 'Качественный хлопок'
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_it_fails_if_category_id_is_missing(): void
    {
        $validator = $this->validate([
            'name' => 'Футболка'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('category_id', $validator->errors()->toArray());
        $this->assertEquals('Необходимо выбрать категорию.', $validator->errors()->first('category_id'));
    }

    public function test_it_fails_if_category_does_not_exist(): void
    {
        $validator = $this->validate([
            'category_id' => 999,
            'name'        => 'Футболка'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Выбранная категория не существует.', $validator->errors()->first('category_id'));
    }

    public function test_it_fails_if_category_has_children(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Одежда'
        ]);

        Category::factory()->create([
            'parent_id' => $parent->id
        ]);

        $validator = $this->validate([
            'category_id' => $parent->id,
            'name'        => 'Футболка'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'Нельзя добавить товар в категорию, у которой есть подкатегории.',
            $validator->errors()->first('category_id')
        );
    }

    public function test_it_fails_if_name_is_missing(): void
    {
        $category = Category::factory()->create();

        $validator = $this->validate([
            'category_id' => $category->id,
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Название товара обязательно.', $validator->errors()->first('name'));
    }

    public function test_it_fails_if_description_is_too_long(): void
    {
        $category = Category::factory()->create();

        $validator = $this->validate([
            'category_id' => $category->id,
            'name'        => 'Футболка',
            'description' => str_repeat('a', 2001) // Лимит 2000
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }
}
