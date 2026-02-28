<?php

namespace Database\Seeders;

use App\Models\{Brand,
    Category,
    Color,
    Gender,
    Measure,
    Price,
    PriceType,
    Product,
    ProductVariant,
    Property,
    PropertyValue,
    Size,
    Stock,
    Warehouse};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::create(['title' => 'Rice Style', 'slug' => 'rice-style']);
        $categories = collect([
            'Футболки',
            'Худи',
            'Толстовки',
            'Свитшоты',
            'Кенгуру'
        ])->map(fn($t) => Category::create([
            'title' => $t,
            'slug' => Str::slug($t)
        ])
        );

        $priceType = PriceType::create(['title' => 'retail']);
        $warehouse = Warehouse::create([
            'title' => 'Главный склад',
            'slug' => 'main-wh',
            'address' => 'ул. Программистов, 101',
        ]);

        // 2. Цвета (Палитра)
        $colors = collect([
            ['title' => 'Черный', 'hex' => '#000000', 'priority' => 1],
            ['title' => 'Белый', 'hex' => '#FFFFFF', 'priority' => 2],
            ['title' => 'Серый меланж', 'hex' => '#BEBEBE', 'priority' => 3],
            ['title' => 'Синий', 'hex' => '#0000FF', 'priority' => 4],
            ['title' => 'Красный', 'hex' => '#FF0000', 'priority' => 5],
        ])->map(fn($c) => Color::create([
            'title' => $c['title'],
            'slug' => Str::slug($c['title']),
            'hex_code' => $c['hex'],
            'priority' => $c['priority']
        ]));

        $sizeList = [
            '98-104' => 10,
            '110-116' => 20,
            '122-128' => 30,
            '134-140' => 40,
            'XS' => 50,
            'S' => 60,
            'M' => 70,
            'L' => 80,
            'XL' => 90,
            'XXL' => 100,
            '3XL' => 110,
        ];

        $sizes = collect($sizeList)->map(fn($priority, $title) => Size::create([
            'title' => $title,
            'slug' => Str::slug($title),
            'priority' => $priority
        ])
        );

        $genderList = [
            'Мужской' => 1,
            'Женский' => 2,
            'Унисекс' => 3,
            'Детский' => 4,
        ];

        $genders = collect($genderList)->map(fn($priority, $title) =>
        Gender::create([
            'title'    => $title,
            'slug'     => Str::slug($title),
            'priority' => $priority
        ])
        );

        // 5. Доп. свойства (для проверки Smart Filter)
        $materialProp = Property::create(['title' => 'Материал', 'slug' => 'material']);
        $cotton = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Хлопок 100%', 'slug' => 'cotton']);

        // 6. Генерация ТОВАРОВ
        foreach ($categories as $category) {
            $product = Product::create([
                'title' => "Базовая модель " . $category->title,
                'slug' => Str::slug($category->title . "-base"),
                'description' => "Качественная одежда из категории " . $category->title,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'is_active' => true,
            ]);

            // Привязываем свойство Хлопок ко всем товарам
            $product->propertyValues()->attach($cotton->id);

            // 7. ГЕНЕРАЦИЯ ВАРИАНТОВ (Комбинаторика)
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    foreach ($genders as $gender) {

                        // Логика пропуска для "умных фильтров":
                        // Например, Синий цвет делаем редким (только XL и только Мужской)
                        if ($color->slug === 'sinii' && ($size->title !== 'XL' || $gender->slug !== 'muzhskoi')) {
                            continue;
                        }

                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'color_id' => $color->id,
                            'size_id' => $size->id,
                            'gender_id' => $gender->id,
                            'sku' => "SKU-" . strtoupper(Str::random(8)),
//                            'is_active' => true,
                            'is_default' => false,
                        ]);

                        // 8. Остатки
                        // Для теста: у Белых товаров всегда большой остаток, у Красных — 0 (для проверки "скрытия")
                        $qty = ($color->slug === 'krasnyi') ? 0 : rand(5, 50);

                        Stock::create([
                            'product_variant_id' => $variant->id,
                            'warehouse_id' => $warehouse->id,
                            'quantity' => $qty
                        ]);

                        // 9. Цены
                        Price::create([
                            'product_variant_id' => $variant->id,
                            'price_type_id' => $priceType->id,
                            'price' => rand(1500, 5000)
                        ]);
                    }
                }
            }
        }
    }
}

