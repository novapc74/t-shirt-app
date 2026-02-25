<?php

namespace Feature\Database;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class DatabaseTriggerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ТЕСТ 1: Триггер запрещает товары там, где уже есть подкатегории.
     */
    public function test_trigger_prevents_adding_product_to_parent_category(): void
    {
        $parent = Category::factory()->create(['name' => 'Одежда']);
        Category::factory()->create([
            'name' => 'Футболки',
            'slug' => 'test',
            'parent_id' => $parent->id
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Нельзя добавить товар: у категории есть подкатегории');

        Product::withoutEvents(function () use ($parent) {
            $product = Product::factory()->make([
                'category_id' => $parent->id,
            ]);
            $product->save();
        });
    }

    /**
     * ТЕСТ 2: Триггер запрещает подкатегорию там, где уже есть товары.
     */
    public function test_trigger_prevents_adding_subcategory_to_category_with_products(): void
    {
        $category = Category::factory()->create(['name' => 'Листовая категория']);
        Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Нельзя создать подкатегорию: в родительской категории уже есть товары');

        Category::withoutEvents(function () use ($category) {
            $subCategory = Category::factory()->make(['parent_id' => $category->id]);
            $subCategory->save();
        });
    }

    /**
     * ТЕСТ 3: Триггер НЕ мешает нормальной работе (у категории нет дочерних элементов).
     */
    public function test_trigger_allows_valid_operations(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        Product::withoutEvents(function () use ($child) {
            $product = Product::factory()->make([
                'category_id' => $child->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
            ]);
            $this->assertTrue($product->save());
        });

        $this->assertDatabaseCount('products', 1);
    }
}
