<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTriggerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ТЕСТ 1: Триггер БД запрещает вставку товара в категорию, у которой есть подкатегории.
     */
    public function test_trigger_prevents_adding_product_to_parent_category_via_sql(): void
    {
        // Создаем структуру через Eloquent (для удобства)
        $parent = Category::create(['title' => 'Одежда', 'slug' => 'odezhda']);
        Category::create(['title' => 'Футболки', 'slug' => 't-shirts', 'parent_id' => $parent->id]);

        // Ожидаем исключение от БД
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Нельзя добавить товар в эту категорию: она имеет подкатегории');

        // Выполняем прямой SQL запрос в обход всех моделей
        DB::statement("
            INSERT INTO products (category_id, title, slug, created_at, updated_at)
            VALUES (?, 'Тестовый товар', 'test-slug', NOW(), NOW())
        ", [$parent->id]);
    }

    /**
     * ТЕСТ 2: Триггер БД запрещает создание подкатегории в категории, где уже есть товары.
     */
    public function test_trigger_prevents_adding_subcategory_to_category_with_products_via_sql(): void
    {
        $category = Category::create(['title' => 'Листовая категория', 'slug' => 'leaf']);

        // Добавляем товар
        DB::statement("
            INSERT INTO products (category_id, title, slug, created_at, updated_at)
            VALUES (?, 'Товар в листе', 'item-leaf', NOW(), NOW())
        ", [$category->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Нельзя создать подкатегорию: родительская категория уже содержит товары');

        // Пытаемся создать подкатегорию через SQL
        DB::statement("
            INSERT INTO categories (parent_id, title, slug, created_at, updated_at)
            VALUES (?, 'Подкатегория', 'sub-slug', NOW(), NOW())
        ", [$category->id]);
    }

    /**
     * ТЕСТ 3: Проверка, что триггер не блокирует валидные операции.
     */
    public function test_trigger_allows_valid_sql_insert(): void
    {
        $category = Category::create(['title' => 'Чистый лист', 'slug' => 'clean-leaf']);

        // Прямая вставка товара в пустую категорию должна пройти
        DB::statement("
            INSERT INTO products (category_id, title, slug, created_at, updated_at)
            VALUES (?, 'Валидный товар', 'valid-p', NOW(), NOW())
        ", [$category->id]);

        $this->assertDatabaseHas('products', ['slug' => 'valid-p']);
    }
}
