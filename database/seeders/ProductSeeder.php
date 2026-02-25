<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Property;
use App\Models\PriceType;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Подготовка базовых данных
        $retailPrice = PriceType::where('slug', 'retail')->first();
        $warehouse = Warehouse::where('name', 'Склад Москва')->first();
        $props = Property::all()->keyBy('slug');

        // Справочник приоритетов для значений (чем меньше число, тем выше в списке)
        $valueWeights = [
            'XS' => 10, 'S' => 20, 'M' => 30, 'L' => 40, 'XL' => 50, 'XXL' => 60,
            '54-56' => 10, '56-58' => 20, '58-60' => 30,
            'male' => 10, 'female' => 20, 'unisex' => 30, 'kids' => 40,
            'Белый' => 10, 'Черный' => 20, 'Серый' => 30, 'Красный' => 40,
            'Синий' => 50, 'Зеленый' => 60, 'Желтый' => 70, 'Оранжевый' => 80,
            'Индиго' => 90, 'Розовый' => 100
        ];

        $colors = [
            '#FFFFFF' => 'Белый', '#000000' => 'Черный', '#FF0000' => 'Красный',
            '#0000FF' => 'Синий', '#00FF00' => 'Зеленый', '#FFFF00' => 'Желтый',
            '#808080' => 'Серый', '#FFC0CB' => 'Розовый', '#FFA500' => 'Оранжевый', '#4B0082' => 'Индиго'
        ];

        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $genders = ['male' => 'Мужской', 'female' => 'Женский', 'unisex' => 'Унисекс', 'kids' => 'Детский'];
        $headSizes = ['54-56', '56-58', '58-60'];

        // --- КАТЕГОРИЯ: ОДЕЖДА ---
        $clothing = Category::updateOrCreate(['name' => 'Одежда']);
        $clothingTypes = [
            'Футболки' => 'T-SHIRT',
            'Худи' => 'HOODIE',
            'Поло' => 'POLO'
        ];

        foreach ($clothingTypes as $typeName => $typeSku) {
            $category = Category::create([
                'name' => $typeName,
                'parent_id' => $clothing->id
            ]);

            $singularName = ($typeName === 'Футболки') ? 'Футболка' : Str::singular($typeName);

            $product = Product::create([
                'name' => "$singularName Basic Collection",
                'category_id' => $category->id
            ]);

            foreach ($genders as $gKey => $gLabel) {
                foreach ($colors as $cHex => $cName) {
                    if (rand(1, 100) > 70) continue;

                    foreach ($sizes as $size) {
                        if (rand(1, 100) > 60) continue;

                        $sku = Str::upper("$typeSku-$gKey-$size-" . Str::after($cHex, '#')) . '-' . Str::random(4);
                        $price = rand(1500, 4500);
                        if (in_array($size, ['XL', 'XXL'])) $price += 500;

                        $qty = (rand(1, 100) > 20) ? rand(5, 50) : 0;

                        $this->createVariant($product, [
                            $props['color']->id   => ['v' => $cHex, 'l' => $cName, 'p' => $valueWeights[$cName] ?? 99],
                            $props['size']->id    => ['v' => $size, 'l' => $size,  'p' => $valueWeights[$size] ?? 99],
                            $props['gender']->id  => ['v' => $gKey, 'l' => $gLabel, 'p' => $valueWeights[$gKey] ?? 99],
                            $props['weight']->id  => ['v' => '0.3', 'l' => '0.3 кг', 'p' => 100],
                        ], $sku, $retailPrice, $warehouse, $price, $qty);
                    }
                }
            }
        }

        // --- КАТЕГОРИЯ: БЕЙСБОЛКИ ---
        $capsCat = Category::create(['name' => 'Бейсболки']);
        $capProduct = Product::create([
            'name' => 'Кепка Sport Tech',
            'category_id' => $capsCat->id
        ]);

        foreach ($colors as $cHex => $cName) {
            if (rand(1, 100) > 80) continue;

            foreach ($headSizes as $hSize) {
                if (rand(1, 100) > 70) continue;

                $sku = Str::upper("CAP-$hSize-" . Str::after($cHex, '#')) . '-' . Str::random(4);
                $price = rand(1200, 2500);
                $qty = (rand(1, 100) > 15) ? rand(3, 30) : 0;

                $this->createVariant($capProduct, [
                    $props['color']->id     => ['v' => $cHex, 'l' => $cName, 'p' => $valueWeights[$cName] ?? 99],
                    $props['head_size']->id => ['v' => $hSize, 'l' => $hSize, 'p' => $valueWeights[$hSize] ?? 99],
                    $props['gender']->id    => ['v' => 'unisex', 'l' => 'Унисекс', 'p' => $valueWeights['unisex']],
                ], $sku, $retailPrice, $warehouse, $price, $qty);
            }
        }
    }

    private function createVariant($product, $propsData, $sku, $priceType, $warehouse, $amount, $quantity): void
    {
        $variant = $product->variants()->create(['sku' => $sku]);

        $propertiesToSave = [];
        foreach ($propsData as $propId => $data) {
            $propertiesToSave[] = [
                'property_id' => $propId,
                'value'       => $data['v'],
                'label'       => $data['l'],
                'priority'    => $data['p'] ?? 99 // Сохраняем приоритет в БД
            ];
        }
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
