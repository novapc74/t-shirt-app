<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceSeeder extends Seeder
{
    /**
     * НАСТРОЙКИ КАСТОМИЗАЦИИ
     */
    public static int $brandsCount = 5;      // Сколько брендов взять из списка ниже
    public static int $propertiesCount = 2;  // Сколько групп свойств взять из списка ниже

    public function run(): void
    {
        // 1. Категории
        $categories = ['Футболки', 'Худи', 'Толстовки', 'Свитшоты', 'Кенгуру'];
        foreach ($categories as $title) {
            DB::table('categories')->updateOrInsert(['slug' => Str::slug($title)], [
                    'title' => $title,
                    'priority' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // 2. Бренды (из фиксированного списка)
        $brandList = [
            'Nike',
            'Adidas',
            'Puma',
            'Reebok',
            'Under Armour',
            'Gucci',
            'Prada',
            'Levi\'s',
            'Stone Island',
            'Lacoste',
        ];

        // Берем срез согласно настройке $brandsCount
        $brandsToSeed = array_slice($brandList, 0, self::$brandsCount);

        foreach ($brandsToSeed as $brand) {
            DB::table('brands')->updateOrInsert(
                ['slug' => Str::slug($brand)], [
                    'title' => $brand,
                    'priority' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Склады и цены
        DB::table('warehouses')->updateOrInsert(['slug' => 'main-wh'], [
            'title' => 'Главный склад',
            'address' => 'ул. Пр, 101',
            'priority' => 0,
            'is_active' => true,
        ]);
        DB::table('price_types')->updateOrInsert(['title' => 'retail'], ['title' => 'retail']);

        // 4. Свойства (из фиксированного списка)
        $propertyList = [
            'Материал' => ['Хлопок', 'Полиэстер', 'Шерсть', 'Вискоза'],
            'Сезон' => ['Лето', 'Зима', 'Демисезон', 'Мульти'],
            'Принт' => ['Логотип', 'Однотонный', 'Графика', 'Полоска'],
            'Посадка' => ['Oversize', 'Regular Fit', 'Slim Fit'],
            'Стиль' => ['Casual', 'Sport', 'Streetwear', 'Classic'],
        ];

        // Берем срез групп свойств согласно настройке $propertiesCount
        $propsToSeed = array_slice($propertyList, 0, self::$propertiesCount, true);

        $i = 1;
        foreach ($propsToSeed as $propTitle => $values) {
            $propId = DB::table('properties')->insertGetId([
                'title' => $propTitle,
                'slug' => Str::slug($propTitle),
                'priority' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $i++;

            $valueEntries = [];
            foreach ($values as $valText) {
                $valueEntries[] = [
                    'property_id' => $propId,
                    'value' => $valText,
                    'slug' => Str::slug($valText)."-".Str::random(3),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('property_values')->insert($valueEntries);
        }

        // 5. Статика: Цвета, Размеры, Гендеры
        $this->seedBaseConstants();
    }

    private function seedBaseConstants(): void
    {
        $colorList = [
            ['Черный', '#000000'],
            ['Белый', '#FFFFFF'],
            ['Серый меланж', '#BEBEBE'],
            ['Графит', '#383838'],
            ['Темно-синий (Navy)', '#000080'],
            ['Бордовый', '#800000'],
            ['Оливковый', '#808000'],
            ['Бежевый (Песочный)', '#F5F5DC'],
            ['Хаки', '#BDB76B'],
            ['Пыльная роза', '#DCAE96'],
            ['Горчичный', '#E1AD01'],
            ['Бутылочный зеленый', '#006A4E'],
            ['Королевский синий', '#4169E1'],
            ['Оранжевый (Сигнал)', '#FF8C00'],
            ['Шоколадный', '#3D1F1F'],
        ];

        foreach ($colorList as $index => $c) {
            DB::table('colors')->updateOrInsert(['slug' => Str::slug($c[0])], [
                'title' => $c[0],
                'hex_code' => $c[1],
                'priority' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sizes = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
        foreach ($sizes as $index => $s) {
            DB::table('sizes')->updateOrInsert(
                ['slug' => Str::slug($s)], // Поиск по slug
                [
                    'title' => $s,
                    'priority' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach (['Мужской', 'Женский', 'Унисекс', 'Детский'] as $index => $g) {
            DB::table('genders')->updateOrInsert(['slug' => Str::slug($g)], [
                'title' => $g,
                'priority' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
