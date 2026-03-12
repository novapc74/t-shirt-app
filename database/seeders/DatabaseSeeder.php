<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Очистка перед тестами
        DB::statement('TRUNCATE filter_vectors RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE product_variants RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE products RESTART IDENTITY CASCADE');

        // 2. Настройка справочников (Бренды, Цвета, Размеры, Свойства)
        // Сделаем немного, чтобы в фильтрах не было каши
        ReferenceSeeder::$brandsCount = 5;
        ReferenceSeeder::$propertiesCount = 10;

        // 3. Настройка товаров
        // Создадим 20 товаров, чтобы глазами видеть результат в выдаче
        ProductStressSeeder::$totalProducts = 20;
        // По 3-5 вариантов на каждый товар, чтобы протестировать перечеркивание цветов
        ProductStressSeeder::$variantsRange = [3, 5];

        $this->info('Наполняю базовые таблицы (Products, Variants, Prices)...');
        $this->call([
            ReferenceSeeder::class,
            ProductStressSeeder::class,
        ]);

        // 4. ДОПОЛНИТЕЛЬНЫЙ ШАГ: Создаем "Тестовую ловушку" для Хлопка и Лета
        // Чтобы гарантированно проверить твой кейс:
        // сделаем так, чтобы ID_СВОЙСТВА=1 (Лето) никогда не пересекалось с ID_СВОЙСТВА=2 (Хлопок)
        $this->createTestScenario();

        // 5. Генерируем ВЕКТОРЫ ВАРИАНТОВ (Твоя новая архитектура)
        $this->info('Генерирую фильтры-векторы по ВАРИАНТАМ...');
        $this->command->call('filter:sync');

        $this->info('Тестовая база готова!');
    }

    private function createTestScenario(): void
    {
        $tableName = 'product_properties'; // Твое реальное имя таблицы

        $this->command->getOutput()->info("Создаю сценарий: Лето (ID 1) не имеет Хлопка (ID 2)...");

        // 1. Находим товары со свойством ID 1 (Лето)
        $summerProductIds = DB::table($tableName)
            ->where('property_value_id', 1)
            ->pluck('product_id');

        // 2. Удаляем у них связь со свойством ID 2 (Хлопок)
        DB::table($tableName)
            ->whereIn('product_id', $summerProductIds)
            ->where('property_value_id', 2)
            ->delete();
    }


    private function info($msg): void
    {
        $this->command->getOutput()->info($msg);
    }
}
