<?php

namespace Feature\Model;

use Exception;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1 - тестируем создание слага.
     */
    public function testGenerateSlugFromCategoryName(): void
    {
        $category = Category::create(['name' => 'Мужские футболки']);

        $this->assertEquals('muzskie-futbolki', $category->slug);
    }

    /**
     * TEST 2 - при изменении названия категории, слаг остается прежним.
     */
    public function testCategoryDoesNotChangeSlugOnUpdate(): void
    {
        $category = Category::create(['name' => 'Старое имя']);
        $originalSlug = $category->slug;

        $category->update(['name' => 'Новое имя']);

        // Проверяем, что имя изменилось, а слаг остался прежним для SEO
        $this->assertEquals('Новое имя', $category->name);
        $this->assertEquals($originalSlug, $category->slug);
    }

    /**
     * TEST 3 - тестируем связи категории с дочерними категориями.
     */
    public function testCategoryHasParentAndChildrenRelations(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertEquals($parent->id, $child->parent->id);
    }

    /**
     * TEST 4 - тестируем наличие поиска категории по слагу для роутов.
     */
    public function testCategoryUsesSlugForRouteModelBinding(): void
    {
        $category = new Category();
        $this->assertEquals('slug', $category->getRouteKeyName());
    }

    /**
     * TEST 5 - нельзя добавить товар, если у категории уже есть дочерние категории.
     */
    public function categoryCannotHaveBothSubcategoriesAndProducts(): void
    {
        $parent = Category::factory()->create();

        $child = Category::factory()->create([
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($parent->children->contains($child));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot add product to a category that has subcategories");

        Product::factory()->create([
            'category_id' => $parent->id,
        ]);
    }

    /**
     * TEST 5 - нельзя добавить категорию, если у категории уже есть дочерние товары.
     */
    public function testCategoryThrowsExceptionIfAddingChildToCategoryThatIsAlreadyAParent(): void
    {
        $parent = Category::factory()->create();

        $childProduct = Product::factory()->create([
            'category_id' => $parent->id,
        ]);

        $this->assertTrue($parent->products->contains($childProduct));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot add subcategory to a category that has products");

        Category::factory()->create([
            'parent_id' => $parent->id
        ]);
    }

    /**
     * TEST 6 - проверка уникальности слага категории.
     */
    public function test_it_generates_unique_slugs_for_same_names()
    {
        $categoryOne = Category::factory()->create([
            'name' => 'Дубль',
        ]);
        $categoryTwo = Category::factory()->create([
            'name' => 'Дубль',
        ]);

        $this->assertEquals('dubl', $categoryOne->slug);
        $this->assertEquals('dubl-1', $categoryTwo->slug);
    }
}
