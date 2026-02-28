<?php

namespace Tests\Unit\Services;

use App\Models\{Product, ProductVariant, Color, PriceType, Category, Brand, Warehouse};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReindexSmartFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reindexes_data_correctly(): void
    {
        // 1. Подготовка данных
        $brand = Brand::create(['title' => 'B', 'slug' => 'b', 'priority' => 0]);
        $cat = Category::create(['title' => 'C', 'slug' => 'c', 'priority' => 0]);
        $color = Color::create(['title' => 'Blue', 'slug' => 'blue', 'hex_code' => '#00f', 'priority' => 1]);
        $priceType = PriceType::create(['title' => 'retail']);
        $wh = Warehouse::create(['title' => 'W', 'slug' => 'w', 'address' => 'A', 'priority' => 0]);

        $product = Product::create([
            'title' => 'T-Shirt',
            'slug' => 't-shirt',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'is_active' => true,
            'description' => '...'
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'sku' => 'SKU1',
            'is_default' => true
        ]);

        // Создаем цену и остаток
        $variant->prices()->create(['price_type_id' => $priceType->id, 'price' => 1500]);
        $variant->stocks()->create(['warehouse_id' => $wh->id, 'quantity' => 5]);

        // 2. Запуск команды
        $this->artisan('shop:reindex')
            ->expectsOutput('Очистка старого индекса...')
            ->expectsOutput('Индексация успешно завершена!')
            ->assertExitCode(0);

        // 3. Проверка результата в БД
        $this->assertDatabaseHas('smart_filter_index', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'property_id' => Color::SMART_FILTER_ID, // 1001
            'property_value_id' => $color->id,
            'price' => 1500.00,
            'stock' => 5,
            'is_active' => true
        ]);
    }

    /**
     * @return void
     */
    public function test_it_truncates_table_before_indexing(): void
    {
        // Вручную вставим "мусорную" запись
        DB::table('smart_filter_index')->insert([
            'product_id' => 999,
            'product_variant_id' => 999,
            'category_id' => 1,
            'brand_id' => 1,
            'property_id' => 1,
            'property_value_id' => 1,
            'price' => 0,
            'stock' => 0
        ]);

        $this->assertEquals(1, DB::table('smart_filter_index')->count());

        // Запуск команды
        $this->artisan('shop:reindex');

        // После запуска (даже если база пустая) мусора быть не должно
        $this->assertDatabaseMissing('smart_filter_index', ['product_id' => 999]);
    }

    /**
     * @return void
     */
    public function test_it_correctly_aggregates_stocks_and_prices(): void
    {
        // 1. Подготовка окружения
        $priceType = PriceType::create(['id' => 1, 'title' => 'retail', 'priority' => 0]); // ID 1 для команды
        $wh1 = Warehouse::create(['title' => 'Sklad 1', 'slug' => 's1', 'address' => 'A1', 'priority' => 0]);
        $wh2 = Warehouse::create(['title' => 'Sklad 2', 'slug' => 's2', 'address' => 'A2', 'priority' => 0]);

        // Создаем товар и вариант
        $cat = Category::create(['title' => 'C', 'slug' => 'c', 'priority' => 0]);
        $brand = Brand::create(['title' => 'B', 'slug' => 'b', 'priority' => 0]);
        $product = Product::create(['title' => 'P', 'slug' => 'p', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true]);

        $color = Color::create(['title' => 'Red', 'slug' => 'red', 'hex_code' => '#f00', 'priority' => 1]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'sku' => 'TEST-SKU']);

        // 2. Создаем ДВА склада с остатками (5 + 10 = 15)
        $variant->stocks()->create(['warehouse_id' => $wh1->id, 'quantity' => 5]);
        $variant->stocks()->create(['warehouse_id' => $wh2->id, 'quantity' => 10]);

        // 3. Создаем цену (1500.50)
        $variant->prices()->create(['price_type_id' => $priceType->id, 'price' => 1500.50]);

        // 4. Запуск команды
        $this->artisan('shop:reindex')->assertExitCode(0);

        // 5. ПРОВЕРКА: в индексе должна быть строка с суммарным остатком и верной ценой
        $this->assertDatabaseHas('smart_filter_index', [
            'product_variant_id' => $variant->id,
            'property_id'        => Color::SMART_FILTER_ID,
            'price'              => 1500.50,
            'stock'              => 15, // 5 + 10
        ]);
    }

    public function test_it_correctly_aggregates_stocks_and_prices_during_reindex(): void
    {
        // 1. Создаем инфраструктуру
        $priceType = PriceType::create(['id' => 1, 'title' => 'retail', 'priority' => 0]);
        $wh1 = Warehouse::create(['title' => 'S1', 'slug' => 's1', 'address' => 'A1', 'priority' => 0]);
        $wh2 = Warehouse::create(['title' => 'S2', 'slug' => 's2', 'address' => 'A2', 'priority' => 0]);

        $cat = Category::create(['title' => 'C', 'slug' => 'c', 'priority' => 0]);
        $brand = Brand::create(['title' => 'B', 'slug' => 'b', 'priority' => 0]);

        $product = Product::create([
            'title' => 'Test', 'slug' => 'test',
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-1',
            'color_id' => Color::create(['title' => 'Red', 'slug' => 'red', 'hex_code' => '#f00', 'priority' => 1])->id
        ]);

        // 2. Создаем ДВА склада (5 + 10 = 15 единиц)
        $variant->stocks()->create(['warehouse_id' => $wh1->id, 'quantity' => 5]);
        $variant->stocks()->create(['warehouse_id' => $wh2->id, 'quantity' => 10]);

        // 3. Создаем цену (2500.50)
        $variant->prices()->create(['price_type_id' => $priceType->id, 'price' => 2500.50]);

        // 4. Запускаем команду
        $this->artisan('shop:reindex')->assertExitCode(0);

        // 5. ПРОВЕРЯЕМ: в индексе должна быть строка с суммой остатков и ценой
        $this->assertDatabaseHas('smart_filter_index', [
            'product_variant_id' => $variant->id,
            'price'              => 2500.50,
            'stock'              => 15, // 5 + 10
        ]);
    }


}

