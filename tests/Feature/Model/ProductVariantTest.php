<?php

namespace Tests\Feature\Model;

use Tests\TestCase;
use App\Models\Price;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Property;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1 - Проверка создания варианта и привязки свойств.
     */
    public function test_it_can_store_and_retrieve_properties(): void
    {
        $property = Property::create([
            'name' => 'Цвет',
            'slug' => 'color'
        ]);

        $variant = ProductVariant::factory()->create();

        $variant->properties()->create([
            'property_id' => $property->id,
            'value' => 'Красный'
        ]);

        $this->assertCount(1, $variant->properties);
        $this->assertEquals('Красный', $variant->properties->first()->value);
        $this->assertEquals('Цвет', $variant->properties->first()->property->name);
    }

    /**
     * TEST 2 - Проверка фильтрации по значению свойства.
     */
    public function test_it_can_filter_variants_by_properties(): void
    {
        $color = Property::create([
            'name' => 'Цвет',
            'slug' => 'color'
        ]);

        // Вариант 1 - Красный
        $redVariant = ProductVariant::factory()->create();
        $redVariant->properties()->create([
            'property_id' => $color->id,
            'value' => 'Red'
        ]);

        // Вариант 2 - Синий
        $blueVariant = ProductVariant::factory()->create();
        $blueVariant->properties()->create([
            'property_id' => $color->id,
            'value' => 'Blue'
        ]);

        // Ищем через whereHas (стандартный способ Laravel для связей)
        $results = ProductVariant::whereHas('properties', function ($query) {
            $query->where('value', 'Red');
        })->get();

        $this->assertCount(1, $results);
        $this->assertEquals($redVariant->id, $results->first()->id);
    }

    /**
     * TEST 3 - Проверка уникальности SKU.
     */
    public function test_it_enforces_unique_sku(): void
    {
        ProductVariant::factory()->create(['sku' => 'UNIQUE-123']);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->create(['sku' => 'UNIQUE-123']);
    }

    /**
     * TEST 4 - Проверка всех связей (Product, Prices, Stocks, Properties).
     */
    public function test_it_has_correct_relations(): void
    {
        $variant = ProductVariant::factory()
            ->has(Price::factory()->count(2))
            ->has(Stock::factory()->count(1))
            ->create();

        $property = Property::create([
            'name' => 'Размер',
            'slug' => 'size'
        ]);

        $variant->properties()->create([
            'property_id' => $property->id,
            'value' => 'XL'
        ]);

        $this->assertInstanceOf(Product::class, $variant->product);
        $this->assertCount(2, $variant->prices);
        $this->assertCount(1, $variant->stocks);
        $this->assertCount(1, $variant->properties);
    }

    /**
     * TEST 5 - Фильтрация по нескольким значениям.
     */
    public function test_it_can_filter_by_multiple_property_values(): void
    {
        $size = Property::create([
            'name' => 'Размер',
            'slug' => 'size'
        ]);

        foreach (['S', 'M', 'L'] as $value) {
            $variant = ProductVariant::factory()->create();
            $variant->properties()->create([
                'property_id' => $size->id,
                'value' => $value
            ]);
        }

        // Ищем варианты с размером S или L
        $results = ProductVariant::whereHas('properties',
            fn($query) => $query->where('property_id', $size->id)->whereIn('value', ['S', 'L'])
        )->get();

        $this->assertCount(2, $results);
    }
}
