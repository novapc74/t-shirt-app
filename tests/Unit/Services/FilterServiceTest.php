<?php

namespace Tests\Unit\Services;

use App\Models\{Brand, Category, Color, Gender, Price, PriceType, Product, ProductVariant, Property, PropertyValue, Size, Stock, Warehouse};
use App\Services\Catalog\DTO\ProductFilterParams;
use App\Services\Catalog\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FilterServiceTest extends TestCase
{
    use RefreshDatabase; // Очищает БД перед каждым тестом

    protected FilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FilterService();

        // Наполняем базу минимальным набором данных для тестов
        $this->seedBasicData();
    }

    /**
     * Тест: Фильтрация по цвету (Системный ID 1001)
     */
    public function test_it_filters_by_color_using_system_id(): void
    {
        $colorId = Color::where('slug', 'sinii')->first()->id;
        $categoryId = Category::where('slug', 'odezhda')->first()->id;

        $params = new ProductFilterParams(
            filters: ['color' => [$colorId]]
        );

        $productIds = $this->service->getFilteredProductIds($params, $categoryId);

        $this->assertContains(
            Product::where('slug', 'blue-tshirt')->first()->id,
            $productIds
        );
    }

    /**
     * Тест: Скрытие товаров с нулевым остатком
     */
    public function test_it_hides_products_with_zero_stock(): void
    {
        $redColorId = Color::where('slug', 'krasnyi')->first()->id;
        $categoryId = Category::where('slug', 'odezhda')->first()->id;

        // Красная футболка имеет stock = 0
        $params = new ProductFilterParams(
            filters: ['color' => [$redColorId]]
        );

        $productIds = $this->service->getFilteredProductIds($params, $categoryId);

        // Должно быть 0 результатов, так как stock > 0
        $this->assertEmpty($productIds);
    }

    /**
     * Тест: Фильтрация по диапазону цен
     */
    public function test_it_filters_by_price_range(): void
    {
        $categoryId = Category::where('slug', 'odezhda')->first()->id;

        $params = new ProductFilterParams(
            minPrice: 1000,
            maxPrice: 2500
        );

        $productIds = $this->service->getFilteredProductIds($params, $categoryId);

        $this->assertNotEmpty($productIds);
        $this->assertContains(
            Product::where('slug', 'blue-tshirt')->first()->id,
            $productIds
        );
    }

    /**
     * Тест: Фильтрация по гендеру (Системный ID 1003)
     */
    public function test_it_filters_by_gender_using_system_id(): void
    {
        $genderId = Gender::where('slug', 'muzhskoi')->first()->id;
        $categoryId = Category::where('slug', 'odezhda')->first()->id;

        $params = new ProductFilterParams(
            filters: ['gender' => [$genderId]]
        );

        $productIds = $this->service->getFilteredProductIds($params, $categoryId);

        $this->assertCount(1, $productIds);
        $this->assertEquals(
            Product::where('slug', 'blue-tshirt')->first()->id,
            $productIds[0]
        );
    }

    /**
     * Наполнение тестовыми данными
     */
    private function seedBasicData(): void
    {
        // 1. Справочники
        $brand = Brand::create(['title' => 'Rice Style', 'slug' => 'rice-style', 'priority' => 0]);
        $cat = Category::create(['title' => 'Одежда', 'slug' => 'odezhda', 'priority' => 0]);
        $priceType = PriceType::create(['title' => 'retail']);
        $warehouse = Warehouse::create(['title' => 'Склад', 'slug' => 'sklad', 'address' => 'Адрес', 'priority' => 0]);

        // 2. Атрибуты
        $blue = Color::create(['title' => 'Синий', 'slug' => 'sinii', 'hex_code' => '#0000FF', 'priority' => 1]);
        $red = Color::create(['title' => 'Красный', 'slug' => 'krasnyi', 'hex_code' => '#FF0000', 'priority' => 2]);
        $sizeL = Size::create(['title' => 'L', 'slug' => 'l', 'priority' => 10]);
        $sizeS = Size::create(['title' => 'S', 'slug' => 's', 'priority' => 5]);
        $male = Gender::create(['title' => 'Мужской', 'slug' => 'muzhskoi', 'priority' => 1]);
        $female = Gender::create(['title' => 'Женский', 'slug' => 'zhenskii', 'priority' => 2]);

        // 3. Свойство Материал
        $materialProp = Property::create(['title' => 'Материал', 'slug' => 'material']);
        $cotton = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Хлопок', 'slug' => 'cotton']);

        // ТОВАР 1: СИНЯЯ МУЖСКАЯ ФУТБОЛКА (В наличии, 2000 руб)
        $p1 = Product::create([
            'title' => 'Синяя футболка', 'slug' => 'blue-tshirt',
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true, 'description' => '...'
        ]);
        $p1->propertyValues()->attach($cotton->id);

        $v1 = ProductVariant::create([
            'product_id' => $p1->id, 'color_id' => $blue->id, 'size_id' => $sizeL->id,
            'gender_id' => $male->id, 'sku' => 'BLUE-L-MALE', 'is_default' => true
        ]);

        $v1->stocks()->create(['warehouse_id' => $warehouse->id, 'quantity' => 10]);
        $v1->prices()->create(['price_type_id' => $priceType->id, 'price' => 2000]);

        // ТОВАР 2: КРАСНЫЙ ЖЕНСКИЙ ХУДИ (Нет в наличии, 5000 руб)
        $p2 = Product::create([
            'title' => 'Красный худи', 'slug' => 'red-hoodie',
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true, 'description' => '...'
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $p2->id, 'color_id' => $red->id, 'size_id' => $sizeS->id,
            'gender_id' => $female->id, 'sku' => 'RED-S-FEMALE', 'is_default' => true
        ]);

        $v2->stocks()->create(['warehouse_id' => $warehouse->id, 'quantity' => 0]);
        $v2->prices()->create(['price_type_id' => $priceType->id, 'price' => 5000]);

        // 4. Запуск индексации
        Artisan::call('shop:reindex');
    }
}
