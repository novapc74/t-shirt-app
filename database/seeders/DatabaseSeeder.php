<?php

namespace Database\Seeders;

use App\Models\{Brand, Category, Color, Gender, Price, PriceType, Product, ProductVariant, Property, PropertyValue, Size, Stock, Warehouse};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Справочники
        $brand = Brand::create(['title' => 'Rice Style', 'slug' => 'rice-style', 'priority' => 0]);
        $categories = collect(['Футболки', 'Худи', 'Толстовки', 'Свитшоты', 'Кенгуру'])->map(fn($t) =>
        Category::create(['title' => $t, 'slug' => Str::slug($t), 'priority' => 0])
        );

        $priceType = PriceType::create(['title' => 'retail']);
        $warehouse = Warehouse::create(['title' => 'Главный склад', 'slug' => 'main-wh', 'address' => 'ул. Пр, 101', 'priority' => 0]);

        // 2. Цвета
        $colors = collect([
            ['title' => 'Черный', 'hex' => '#000000', 'priority' => 1],
            ['title' => 'Белый', 'hex' => '#FFFFFF', 'priority' => 2],
            ['title' => 'Серый меланж', 'hex' => '#BEBEBE', 'priority' => 3],
            ['title' => 'Синий', 'hex' => '#0000FF', 'priority' => 4],
            ['title' => 'Красный', 'hex' => '#FF0000', 'priority' => 5],
        ])->map(fn($c) => Color::create(['title' => $c['title'], 'slug' => Str::slug($c['title']), 'hex_code' => $c['hex'], 'priority' => $c['priority']]));

        // 3. Размеры
        $sizes = collect(['98-104', '110-116', '122-128', 'XS', 'S', 'M', 'L', 'XL', 'XXL'])->map(fn($s, $i) =>
        Size::create(['title' => $s, 'slug' => Str::slug($s), 'priority' => ($i + 1) * 10])
        );

        // 4. Гендер
        $genders = collect(['Мужской', 'Женский', 'Унисекс', 'Детский'])->map(fn($g, $i) =>
        Gender::create(['title' => $g, 'slug' => Str::slug($g), 'priority' => $i + 1])
        );

        // 5. Свойства
        $materialProp = Property::create(['title' => 'Материал', 'slug' => 'material', 'priority' => 0]);
        $cotton = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Хлопок 100%', 'slug' => 'cotton']);
        $poly = PropertyValue::create(['property_id' => $materialProp->id, 'value' => 'Полиэстер', 'slug' => 'polyester']);

        // 6. Генерация ТОВАРОВ (по 3 модели на категорию для объема)
        foreach ($categories as $category) {
            foreach (['Premium', 'Basic', 'Sport'] as $type) {
                $product = Product::create([
                    'title' => "$type {$category->title}",
                    'slug' => Str::slug("$type-{$category->title}-" . Str::random(3)),
                    'description' => "Описание для $type {$category->title}",
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'is_active' => true,
                ]);

                // Рандомно вешаем свойство
                $product->propertyValues()->attach(rand(0, 1) ? $cotton->id : $poly->id);

                // 7. ГЕНЕРАЦИЯ ВАРИАНТОВ (С рандомными пропусками)
                foreach ($colors as $color) {
                    // Пропускаем цвета рандомно для каждой модели, чтобы фильтры были "умными"
                    if (rand(1, 10) > 7) continue;

                    foreach ($sizes as $size) {
                        // Не у каждой модели есть все размеры
                        if (rand(1, 10) > 6) continue;

                        foreach ($genders as $gender) {
                            // Исключаем нелогичные пары (напр. Детский XXL)
                            if ($gender->slug === 'detskii' && in_array($size->title, ['L', 'XL', 'XXL'])) continue;

                            $variant = ProductVariant::create([
                                'product_id' => $product->id,
                                'color_id' => $color->id,
                                'size_id' => $size->id,
                                'gender_id' => $gender->id,
                                'sku' => "SKU-" . strtoupper(Str::random(8)),
                                'is_default' => false,
                            ]);

                            // 8. Остатки (у некоторых 0, чтобы проверить "is_available")
                            // 20% шанс, что товара нет в наличии
                            $quantity = (rand(1, 10) > 8) ? 0 : rand(1, 100);

                            Stock::create([
                                'product_variant_id' => $variant->id,
                                'warehouse_id' => $warehouse->id,
                                'quantity' => $quantity
                            ]);

                            // 9. Цены (широкий диапазон для фильтра цены)
                            Price::create([
                                'product_variant_id' => $variant->id,
                                'price_type_id' => $priceType->id,
                                'price' => rand(900, 7000)
                            ]);
                        }
                    }
                }
            }
        }

        // В конце запускаем реиндекс, чтобы сразу видеть результат
        \Illuminate\Support\Facades\Artisan::call('shop:reindex');
    }
}
