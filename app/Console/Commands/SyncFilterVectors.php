<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncFilterVectors extends Command
{
    protected $signature = 'filter:sync';
    protected $description = 'Полная синхронизация векторов фильтрации';

    public function handle(): void
    {
        $this->info('--- Старт синхронизации векторов ---');

        // Очищаем таблицу полностью
        DB::table('filter_vectors')->truncate();

        // 1. Из таблицы PRODUCTS (Категории, Бренды)
        $this->syncFromProducts('category', 'category_id');
        $this->syncFromProducts('brand', 'brand_id');

        // 2. Из таблицы PRODUCT_VARIANTS (Цвета, Размеры, Гендеры)
        $this->syncFromVariantAttr('color', 'color_id');
        $this->syncFromVariantAttr('size', 'size_id');
        $this->syncFromVariantAttr('gender', 'gender_id'); // Добавил гендеры

        // 3. Динамические свойства (Характеристики)
        $this->syncProperties();

        // 4. Системные фильтры (Наличие)
        $this->refreshSystemStockVector();

        $this->info('--- Синхронизация успешно завершена ---');
    }

    private function syncFromProducts(string $type, string $column): void
    {
        $this->info("Обработка: $type...");
        DB::statement("
            INSERT INTO filter_vectors (entity_type, entity_id, variant_ids)
            SELECT :type, p.$column, uniq(sort(array_agg(pv.id)::int4[]))
            FROM products p
            JOIN product_variants pv ON pv.product_id = p.id
            WHERE p.$column IS NOT NULL
            GROUP BY p.$column
        ", ['type' => $type]);
    }

    private function syncFromVariantAttr(string $type, string $column): void
    {
        $this->info("Обработка: $type...");
        DB::statement("
            INSERT INTO filter_vectors (entity_type, entity_id, variant_ids)
            SELECT :type, $column, uniq(sort(array_agg(id)::int4[]))
            FROM product_variants
            WHERE $column IS NOT NULL
            GROUP BY $column
        ", ['type' => $type]);
    }

    private function syncProperties(): void
    {
        $this->info("Обработка: динамические свойства (через связку таблиц)...");

        // Очищаем старые свойства, если они были под другими именами
        // (опционально, так как truncate в начале handle уже всё очистил)

        DB::statement("
        INSERT INTO filter_vectors (entity_type, entity_id, variant_ids)
        SELECT
            prop.slug as entity_type,
            pp.property_value_id as entity_id,
            uniq(sort(array_agg(pv.id)::int4[])) as variant_ids
        FROM product_properties pp
        JOIN property_values pv_vals ON pv_vals.id = pp.property_value_id
        JOIN properties prop ON prop.id = pv_vals.property_id
        JOIN product_variants pv ON pv.product_id = pp.product_id
        WHERE pp.property_value_id IS NOT NULL
        GROUP BY prop.slug, pp.property_value_id
    ");
    }



    public function refreshSystemStockVector(): void
    {
        $this->info("Обработка: system (наличие)...");
        DB::statement("
            INSERT INTO filter_vectors (entity_type, entity_id, variant_ids)
            SELECT 'system', 1, COALESCE(uniq(sort(array_agg(DISTINCT product_variant_id)::int4[])), '{}'::int4[])
            FROM prices
            WHERE product_variant_id IS NOT NULL
            GROUP BY 1, 2
            ON CONFLICT (entity_type, entity_id) DO UPDATE SET variant_ids = EXCLUDED.variant_ids
        ");
    }
}
