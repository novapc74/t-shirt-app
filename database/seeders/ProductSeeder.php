<?php

namespace Database\Seeders;

use App\Models\{Product, Category, Property, PriceType, Warehouse, ProductType, Brand};
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Базовые справочники
        $retailPrice = PriceType::where('slug', 'retail')->first();
        $warehouse = Warehouse::where('name', 'Склад Москва')->first();
        $props = Property::all()->keyBy('slug');

        // 1. СОЗДАНИЕ ТИПОВ ТОВАРОВ
        $typesData = [
            'Футболки'   => 'T-SHIRT',
            'Худи'       => 'HOODIE',
            'Свитшоты'   => 'SWEATSHIRT',
            'Кенгуру'    => 'KANGAROO',
            'Толстовки'  => 'JACKET',
        ];

        $types = collect($typesData)->mapWithKeys(function ($sku, $name) {
            return [$sku => ProductType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            )];
        });

        // 2. СОЗДАНИЕ БРЕНДОВ
        $brandsData = ['Nike', 'Adidas', 'Puma', 'Sol\'s', 'Fruit of the Loom'];
        $brands = collect($brandsData)->map(function ($name) {
            return Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        });

        // 3. ЦВЕТА
        $colors = [
            '#FFFFFF' => 'Белый', '#000000' => 'Черный', '#808080' => 'Серый',
            '#FF0000' => 'Красный', '#0000FF' => 'Синий', '#FFFF00' => 'Желтый',
            '#FFC0CB' => 'Розовый', '#00FFFF' => 'Бирюзовый'
        ];

        // 4. РАЗМЕРНЫЕ СЕТКИ
        $adultSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $kidsSizes  = ['98', '110', '122', '134', '146', '158'];

        // 5. ГЕНДЕРЫ
        $genders = [
            'male'   => 'Мужской',
            'female' => 'Женский',
            'unisex' => 'Унисекс',
            'kids'   => 'Детский'
        ];

        $valueWeights = [
            'kids' => 5, 'male' => 10, 'female' => 20, 'unisex' => 30,
            '98' => 1, '110' => 2, '122' => 3, '134' => 4, '146' => 5, '158' => 6,
            'XS' => 10, 'S' => 20, 'M' => 30, 'L' => 40, 'XL' => 50
        ];

        $clothingCategory = Category::updateOrCreate(['name' => 'Одежда']);

        // 6. ГЕНЕРАЦИЯ ТОВАРОВ
        foreach ($typesData as $typeName => $typeSku) {

            // Для каждого типа создадим товары разных брендов
            foreach ($brands as $brand) {

                $product = Product::create([
                    'name'            => "{$brand->name} {$typeName} Basic",
                    'category_id'     => $clothingCategory->id,
                    'product_type_id' => $types[$typeSku]->id, // Связь Many-to-One
                    'brand_id'        => $brand->id,            // Связь Many-to-One
                ]);

                foreach ($genders as $gKey => $gLabel) {
                    $currentSizeGrid = ($gKey === 'kids') ? $kidsSizes : $adultSizes;

                    foreach ($colors as $cHex => $cName) {
                        // Вероятность наличия цвета (чтобы не раздувать базу до гигантских размеров)
                        if (rand(1, 100) > 70) continue;

                        foreach ($currentSizeGrid as $size) {
                            if (rand(1, 100) > 50) continue;

                            $sku = Str::upper("{$brand->slug}-{$typeSku}-{$gKey}-{$size}-" . Str::after($cHex, '#')) . '-' . Str::random(4);

                            // Базовая цена зависит от типа и бренда
                            $priceModifier = ($brand->name === 'Nike' || $brand->name === 'Adidas') ? 1.5 : 1.0;
                            $basePrice = ($typeName === 'Футболки') ? 1500 : 3500;

                            $price = ($gKey === 'kids') ? ($basePrice * 0.7) : $basePrice;
                            $price = $price * $priceModifier;

                            if (in_array($size, ['XL', 'XXL', '158'])) $price += 300;

                            $this->createVariant($product, [
                                $props['color']->id   => ['v' => $cHex, 'l' => $cName, 'p' => $valueWeights[$cName] ?? 99],
                                $props['size']->id    => ['v' => $size, 'l' => $size,  'p' => $valueWeights[$size] ?? 99],
                                $props['gender']->id  => ['v' => $gKey, 'l' => $gLabel, 'p' => $valueWeights[$gKey] ?? 99],
                            ], $sku, $retailPrice, $warehouse, (int)$price, rand(5, 50));
                        }
                    }
                }
            }
        }
    }

    private function createVariant($product, $propsData, $sku, $priceType, $warehouse, $amount, $quantity): void
    {
        $variant = $product->variants()->create(['sku' => $sku]);

        $propertiesToSave = collect($propsData)->map(fn($data, $propId) => [
            'property_id' => $propId,
            'value'       => $data['v'],
            'label'       => $data['l'],
            'priority'    => $data['p']
        ])->values()->toArray();

        $variant->properties()->createMany($propertiesToSave);

        $variant->prices()->create([
            'price_type_id' => $priceType->id,
            'amount'        => $amount,
            'currency'      => 'RUB'
        ]);

        $variant->stocks()->create([
            'warehouse_id' => $warehouse->id,
            'quantity'     => $quantity
        ]);
    }
}
