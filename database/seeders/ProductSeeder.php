<?php

namespace Database\Seeders;

use App\Models\{Product, Category, Property, PriceType, Warehouse, ProductType};
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $retailPrice = PriceType::where('slug', 'retail')->first();
        $warehouse = Warehouse::where('name', 'Склад Москва')->first();
        $props = Property::all()->keyBy('slug');

        $typesData = [
            'Футболки'  => 'T-SHIRT',
            'Худи'      => 'HOODIE',
            'Свитшоты'  => 'SWEATSHIRT',
            'Кенгуру'   => 'KANGAROO',
        ];

        $types = collect($typesData)->mapWithKeys(fn($sku, $name) => [$sku => ProductType::updateOrCreate(['name' => $name])]);

        // 1. ЦВЕТА
        $colors = [
            '#FFFFFF' => 'Белый', '#000000' => 'Черный', '#808080' => 'Серый',
            '#FF0000' => 'Красный', '#0000FF' => 'Синий', '#FFFF00' => 'Желтый',
            '#FFC0CB' => 'Розовый', '#00FFFF' => 'Бирюзовый'
        ];

        // 2. РАЗМЕРНЫЕ СЕТКИ
        $adultSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $kidsSizes  = ['98', '110', '122', '134', '146', '158']; // Рост в см

        // 3. ГЕНДЕРЫ (Добавили детей)
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

        foreach ($typesData as $typeName => $typeSku) {
            $product = Product::create([
                'name' => "$typeName Family Collection",
                'category_id' => $clothingCategory->id
            ]);

            $product->productTypes()->attach($types[$typeSku]->id);

            foreach ($genders as $gKey => $gLabel) {
                // Выбираем сетку в зависимости от гендера
                $currentSizeGrid = ($gKey === 'kids') ? $kidsSizes : $adultSizes;

                foreach ($colors as $cHex => $cName) {
                    if (rand(1, 100) > 75) continue;

                    foreach ($currentSizeGrid as $size) {
                        if (rand(1, 100) > 60) continue;

                        $sku = Str::upper("$typeSku-$gKey-$size-" . Str::after($cHex, '#')) . '-' . Str::random(4);

                        // Детские вещи обычно дешевле
                        $basePrice = ($typeName === 'Футболки') ? 1900 : 3500;
                        $price = ($gKey === 'kids') ? ($basePrice * 0.7) : $basePrice;

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
        $variant->prices()->create(['price_type_id' => $priceType->id, 'amount' => $amount, 'currency' => 'RUB']);
        $variant->stocks()->create(['warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }
}
