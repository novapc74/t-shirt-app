<?php

namespace Feature\Model;

use Exception;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1 - проверяем создание сдлага у товара
     */
    public function testCreateProduct(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'category_id' => $category->id,
        ]);

        $this->assertEquals('test-product', $product->slug);
    }

    /**
     * TEST 2 - при изменении названия товара, слаг остается прежним.
     */
    public function testProductDoesNotChangeSlugOnUpdate(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Старое имя',
            'category_id' => $category->id,
        ]);
        $originalSlug = $product->slug;

        $product->update(['name' => 'Новое имя']);

        // Проверяем, что имя изменилось, а слаг остался прежним для SEO
        $this->assertEquals('Новое имя', $product->name);
        $this->assertEquals($product->slug, $originalSlug);
    }

    /**
     * TEST 3 - проверка уникальности слага товара.
     */
    public function test_it_generates_unique_slugs_for_same_names()
    {
        $category = Category::factory()->create();

        $productOne = Product::factory()->create([
            'name' => 'Дубль',
            'category_id' => $category->id,
        ]);
        $productTwo = Product::factory()->create([
            'name' => 'Дубль',
            'category_id' => $category->id,
        ]);

        $this->assertEquals('dubl', $productOne->slug);
        $this->assertEquals('dubl-1', $productTwo->slug);
    }

    /**
     * TEST 4 - проверка на updating в booted().
     */
    public function test_it_prevents_moving_product_to_category_with_children()
    {
        $validCategory = Category::factory()->create();
        $invalidCategory = Category::factory()->create();

        Category::factory()->create([
            'parent_id' => $invalidCategory->id
        ]);

        $product = Product::factory()->create([
            'category_id' => $validCategory->id
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot add product to a category that has subcategories');

        $product->update([
            'category_id' => $invalidCategory->id
        ]);
    }

    /**
     * TEST 5 - поиск продукта по слагу
     */
    public function test_it_can_be_resolved_by_slug()
    {
        $product = Product::factory()->create([
            'name' => 'Find Me'
        ]);

        $found = Product::where('slug', 'find-me')->first();

        $this->assertModelExists($found);
        $this->assertEquals($product->id, $found->id);
    }
}
