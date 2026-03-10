<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Кастомная настройка перед запуском
        ReferenceSeeder::$brandsCount = 2;     // Нужно 20 брендов
        ReferenceSeeder::$propertiesCount = 2; // Нужно 10 свойств

        ProductStressSeeder::$totalProducts = 12; // Генерим 5к товаров
        ProductStressSeeder::$variantsRange = [5, 20]; // От 1 до 5 вариантов на товар

        $this->call([
            ReferenceSeeder::class,
            ProductStressSeeder::class,
        ]);

        $this->command->call('catalog:reindex');
    }
}
