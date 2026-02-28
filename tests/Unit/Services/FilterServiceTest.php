<?php

namespace Tests\Unit\Services;

namespace Tests\Unit\Services;

use App\Services\Catalog\FilterService;
use Artisan;
use App\Models\{Color,
    Property,
    PropertyValue,
    Size,
    Gender,
    Product,
    ProductVariant,
    Stock,
    Price,
    PriceType,
    Category,
    Brand,
    Warehouse};
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * @return void
     */
    public function test_it_filters_by_color_using_system_id(): void
    {
        // 1. У нас есть синяя футболка (ID_COLOR = 1001)
        // 2. Вызываем фильтр
        $filters = [
            'attrs' => [
                'color' => [Color::where('slug', 'sinii')->first()->id]
            ]
        ];

        $productIds = $this->service->getFilteredProductIds($filters);

        // 3. Проверяем, что нашелся именно тот товар
        $this->assertCount(1, $productIds);
        $this->assertEquals(Product::where('slug', 'blue-tshirt')->first()->id, $productIds[0]);
    }

    /**
     * @return void
     */
    public function test_it_hides_products_with_zero_stock(): void
    {
        // Красная футболка имеет stock = 0
        $filters = [
            'attrs' => [
                'color' => [Color::where('slug', 'krasnyi')->first()->id]
            ]
        ];

        $productIds = $this->service->getFilteredProductIds($filters);

        // Должно быть 0 результатов, так как в FilterService стоит ->where('stock', '>', 0)
        $this->assertEmpty($productIds);
    }

    /**
     * @return void
     */
    public function test_it_filters_by_price_range(): void
    {
        $filters = [
            'price_min' => 1000,
            'price_max' => 2500
        ];

        $productIds = $this->service->getFilteredProductIds($filters);

        // Проверяем, что попали только товары в этом диапазоне
        $this->assertNotEmpty($productIds);
        foreach($productIds as $id) {
            $price = Price::where('product_variant_id', Product::find($id)->variants->first()->id)->first()->price;
            $this->assertTrue($price >= 1000 && $price <= 2500);
        }
    }

    /**
     * @return void
     */
    private function seedBasicData(): void
    {
        // 1. Базовые справочники
        $brand = Brand::create(['title' => 'Rice Style', 'slug' => 'rice-style', 'priority' => 0]);
        $cat = Category::create(['title' => 'Одежда', 'slug' => 'odezhda', 'priority' => 0]);
        $priceType = PriceType::create(['title' => 'retail']);
        $warehouse = Warehouse::create(['title' => 'Склад', 'slug' => 'sklad', 'address' => 'Адрес', 'priority' => 0]);

        // 2. Системные атрибуты
        $blue = Color::create(['title' => 'Синий', 'slug' => 'sinii', 'hex_code' => '#0000FF', 'priority' => 1]);
        $red = Color::create(['title' => 'Красный', 'slug' => 'krasnyi', 'hex_code' => '#FF0000', 'priority' => 2]);

        $sizeL = Size::create(['title' => 'L', 'slug' => 'l', 'priority' => 10]);
        $sizeS = Size::create(['title' => 'S', 'slug' => 's', 'priority' => 5]);

        $male = Gender::create(['title' => 'Мужской', 'slug' => 'muzhskoi', 'priority' => 1]);
        $female = Gender::create(['title' => 'Женский', 'slug' => 'zhenskii', 'priority' => 2]);

        // 3. Пользовательское свойство (Материал)
        $materialProp = Property::create(['title' => 'Материал', 'slug' => 'material']);
        $cotton = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Хлопок', 'slug' => 'cotton']);
        $polyester = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Полиэстер', 'slug' => 'polyester']);

        // --- ТОВАР 1: СИНЯЯ МУЖСКАЯ ФУТБОЛКА (В наличии, 2000 руб, Хлопок) ---
        $p1 = Product::create([
            'title' => 'Синяя футболка', 'slug' => 'blue-tshirt',
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true, 'description' => '...'
        ]);
        $p1->propertyValues()->attach($cotton->id);

        $v1 = ProductVariant::create([
            'product_id' => $p1->id,
            'color_id' => $blue->id,
            'size_id' => $sizeL->id,
            'gender_id' => $male->id,
            'sku' => 'BLUE-L-MALE',
        ]);

        Stock::create(['product_variant_id' => $v1->id, 'warehouse_id' => $wh->id ?? $warehouse->id, 'quantity' => 10]);
        Price::create(['product_variant_id' => $v1->id, 'price_type_id' => $priceType->id, 'price' => 2000]);


        // --- ТОВАР 2: КРАСНЫЙ ЖЕНСКИЙ ХУДИ (Нет в наличии, 5000 руб, Полиэстер) ---
        $p2 = Product::create([
            'title' => 'Красный худи', 'slug' => 'red-hoodie',
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'is_active' => true, 'description' => '...'
        ]);
        $p2->propertyValues()->attach($polyester->id);

        $v2 = ProductVariant::create([
            'product_id' => $p2->id, 'color_id' => $red->id, 'size_id' => $sizeS->id,
            'gender_id' => $female->id, 'sku' => 'RED-S-FEMALE', 'is_active' => true
        ]);

        // Остаток 0 — этот товар FilterService должен скрывать
        Stock::create(['product_variant_id' => $v2->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);
        Price::create(['product_variant_id' => $v2->id, 'price_type_id' => $priceType->id, 'price' => 5000]);

        // 4. ЗАПУСК ИНДЕКСАЦИИ
        // Без этого шага таблица smart_filter_index будет пустой, и тесты не пройдут
        Artisan::call('shop:reindex');
    }


    public function test_it_filters_by_gender_using_system_id(): void
    {
        $gender = Gender::where('slug', 'muzhskoi')->first();

        $filters = [
            'attrs' => [
                'gender' => [$gender->id]
            ]
        ];

        $productIds = $this->service->getFilteredProductIds($filters);

        // Должен найтись только Синий товар
        $this->assertCount(1, $productIds);
        $this->assertEquals(
            Product::where('slug', 'blue-tshirt')->first()->id,
            $productIds[0]
        );
    }
}

