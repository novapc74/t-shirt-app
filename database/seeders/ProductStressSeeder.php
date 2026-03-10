<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductStressSeeder extends Seeder
{
    public static int $totalProducts = 12; // Увеличил для наглядности фильтров
    public static array $variantsRange = [5, 15]; // Оптимально для тестов
    private int $batchSize = 10;

    public function run(): void
    {
        DB::connection()->disableQueryLog();

        // Загружаем справочники
        $categories = DB::table('categories')->pluck('title', 'id')->toArray();
        $brandIds = DB::table('brands')->pluck('id')->toArray();
        $colorIds = DB::table('colors')->pluck('id')->toArray();
        $genderIds = DB::table('genders')->pluck('id')->toArray();
        $sizeIds = DB::table('sizes')->pluck('id')->toArray();
        $propertyValues = DB::table('property_values')->get()->groupBy('property_id');

        $warehouseId = DB::table('warehouses')->value('id') ?? 1;
        $priceTypeId = DB::table('price_types')->value('id') ?? 1;

        for ($i = 0; $i < self::$totalProducts; $i++) {
            $catId = array_rand($categories);
            $title = "Товар #" . $i . " " . Str::random(4);

            $productId = DB::table('products')->insertGetId([
                'title' => $title,
                'slug' => Str::slug($title) . '-' . Str::random(5),
                'category_id' => $catId,
                'brand_id' => $brandIds[array_rand($brandIds)],
                'is_active' => true,
                'created_at' => now(),
            ]);

            // --- 1. СМАРТ-СВОЙСТВА (PropVal) ---
            // Ограничиваем: каждый товар имеет только 1-2 свойства,
            // чтобы выбор "Материал: Хлопок" реально отсекал товары
            $propsToInsert = [];
            $randomProps = $propertyValues->random(rand(1, 2));
            foreach ($randomProps as $vals) {
                $propsToInsert[] = [
                    'product_id' => $productId,
                    'property_value_id' => $vals->random()->id,
                ];
            }
            DB::table('product_properties')->insert($propsToInsert);

            // --- 2. СМАРТ-ВАРИАНТЫ (Color, Size, Gender) ---
            // ОГРАНИЧЕНИЕ: Для этого товара выбираем только 2 случайных цвета из всех доступных.
            // Это гарантирует, что "Цвета в разных товарах не совпадают" (будут пересекаться редко)
            $availableColors = (array) array_rand(array_flip($colorIds), min(2, count($colorIds)));
            $availableGenders = (array) array_rand(array_flip($genderIds)); // Только один гендер на товар

            $variantsToInsert = [];
            foreach ($availableColors as $cId) {
                // Каждый цвет представлен не во всех размерах
                $subsetSizes = (array) array_rand(array_flip($sizeIds), rand(2, 4));

                foreach ($subsetSizes as $sId) {
                    foreach ($availableGenders as $gId) {
                        $variantsToInsert[] = [
                            'product_id' => $productId,
                            'color_id' => $cId,
                            'size_id' => $sId,
                            'gender_id' => $gId,
                            'sku' => "SKU-$productId-$cId-$sId-" . Str::random(3),
                        ];
                    }
                }
            }

            // Вставляем варианты пачкой
            DB::table('product_variants')->insert($variantsToInsert);

            // --- 3. ЦЕНЫ И СТОКИ ---
            // Получаем ID вариантов именно этого товара
            $vIds = DB::table('product_variants')->where('product_id', $productId)->pluck('id');

            $prices = [];
            $stocks = [];
            // Генерируем БАЗОВУЮ цену для товара, чтобы варианты стоили примерно одинаково (+/- разброс)
            $basePrice = rand(10, 100) * 100;

            foreach ($vIds as $vId) {
                $prices[] = [
                    'product_variant_id' => $vId,
                    'price_type_id' => $priceTypeId,
                    'price' => $basePrice + (rand(-5, 10) * 100), // Разные цены для разных SKU
                    'created_at' => now(),
                ];
                $stocks[] = [
                    'product_variant_id' => $vId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => rand(0, 20), // Включаем 0, чтобы проверить фильтр "в наличии"
                ];
            }
            DB::table('prices')->insert($prices);
            DB::table('stocks')->insert($stocks);
        }
    }
}
