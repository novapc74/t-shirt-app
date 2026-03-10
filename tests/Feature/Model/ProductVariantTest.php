<?php

namespace Tests\Feature\Model;

use Tests\TestCase;
use App\Models\Price;
use App\Models\Stock;
use App\Models\Color;
use App\Models\Size;
use App\Models\Gender;
use App\Models\Product;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1 - Проверка системных свойств варианта (Цвет, Размер, Гендер).
     */
    public function test_it_has_system_properties(): void
    {
        $color = Color::create(['title' => 'Черный', 'slug' => 'black', 'hex_code' => '#000', 'priority' => 0]);
        $size = Size::create(['title' => 'XL', 'slug' => 'xl', 'priority' => 10]);
        $gender = Gender::create(['title' => 'Мужской', 'slug' => 'male', 'priority' => 0]);

        $variant = ProductVariant::factory()->create([
            'color_id' => $color->id,
            'size_id' => $size->id,
            'gender_id' => $gender->id,
        ]);

        $this->assertEquals('Черный', $variant->color->title);
        $this->assertEquals('XL', $variant->size->title);
        $this->assertEquals('Мужской', $variant->gender->title);
    }

    /**
     * TEST 2 - Проверка доступа к свойствам родительского товара.
     */
    public function test_it_can_access_parent_product_properties(): void
    {
        $product = Product::factory()->create();
        $property = Property::create(['title' => 'Материал', 'slug' => 'material', 'priority' => 0]);
        $value = PropertyValue::create([
            'property_id' => $property->id,
            'value' => 'Хлопок 100%',
            'slug' => 'cotton'
        ]);

        // Привязываем свойство к продукту (через таблицу product_properties)
        $product->propertyValues()->attach($value->id);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->assertCount(1, $variant->product->propertyValues);
        $this->assertEquals('Хлопок 100%', $variant->product->propertyValues->first()->value);
    }

    /**
     * TEST 3 - Проверка фильтрации вариантов по системным свойствам.
     */
    public function test_it_can_filter_variants_by_system_properties(): void
    {
        $black = Color::create(['title' => 'Black', 'slug' => 'black', 'hex_code' => '#000', 'priority' => 0]);
        $white = Color::create(['title' => 'White', 'slug' => 'white', 'hex_code' => '#fff', 'priority' => 0]);

        ProductVariant::factory()->create(['color_id' => $black->id]);
        ProductVariant::factory()->create(['color_id' => $white->id]);

        $results = ProductVariant::where('color_id', $black->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($black->id, $results->first()->color_id);
    }

    /**
     * TEST 4 - Проверка уникальности SKU.
     */
    public function test_it_enforces_unique_sku(): void
    {
        ProductVariant::factory()->create(['sku' => 'UNIQUE-123']);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->create(['sku' => 'UNIQUE-123']);
    }

    /**
     * TEST 5 - Проверка связей с ценами и остатками.
     */
    public function test_it_has_prices_and_stocks_relations(): void
    {
        $variant = ProductVariant::factory()
            ->has(Price::factory()->count(2))
            ->has(Stock::factory()->count(1))
            ->create();

        $this->assertInstanceOf(Product::class, $variant->product);
        $this->assertCount(2, $variant->prices);
        $this->assertCount(1, $variant->stocks);
    }

    /**
     * TEST 6 - Поиск варианта через свойства продукта (Материал).
     */
    public function test_it_can_be_found_by_product_properties(): void
    {
        $property = Property::create(['title' => 'Материал', 'slug' => 'material', 'priority' => 0]);
        $cotton = PropertyValue::create(['property_id' => $property->id, 'value' => 'Cotton', 'slug' => 'cotton']);

        $product = Product::factory()->create();
        $product->propertyValues()->attach($cotton->id);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Ищем вариант товара, у которого продукт сделан из хлопка
        $results = ProductVariant::whereHas('product.propertyValues', function ($query) use ($cotton) {
            $query->where('property_values.id', $cotton->id);
        })->get();

        $this->assertCount(1, $results);
        $this->assertEquals($variant->id, $results->first()->id);
    }
}
