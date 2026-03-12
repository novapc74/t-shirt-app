<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncFilterVectors extends Command
{
    protected $signature = 'filter:sync';
    protected $description = 'Сборка векторов ID ВАРИАНТОВ (Бренды, Категории из products)';

    public function handle(): void
    {
        $this->info('--- Старт синхронизации векторов ВАРИАНТОВ ---');

        DB::table('filter_vectors')->truncate();

        // 1. КАТЕГОРИИ (из таблицы products)
        $this->syncFromProducts('category', 'category_id');

        // 2. БРЕНДЫ (из таблицы products)
        $this->syncFromProducts('brand', 'brand_id');

        // 3. ЦВЕТА И РАЗМЕРЫ (из таблицы product_variants)
        $this->syncFromVariantAttr('color', 'color_id');
        $this->syncFromVariantAttr('size', 'size_id');

        // 4. ДИНАМИЧЕСКИЕ СВОЙСТВА (из твоей product_properties)
        $this->syncProperties();

        $this->info('--- Синхронизация успешно завершена ---');
    }

    /**
     * Свойства из таблицы PRODUCTS (Бренды, Категории)
     * Берем ID всех вариантов для каждого товара
     */
    private function syncFromProducts(string $type, string $column): void
    {
        $this->info("Обработка: $type...");

        $data = DB::table('products')
            ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->select("products.$column as entity_id", DB::raw('array_agg(DISTINCT product_variants.id) as v_ids'))
            ->whereNotNull("products.$column")
            ->groupBy("products.$column")
            ->get();

        $this->insertBatch($type, $data);
    }

    /**
     * Свойства из таблицы PRODUCT_VARIANTS (Цвета, Размеры)
     */
    private function syncFromVariantAttr(string $type, string $column): void
    {
        $this->info("Обработка: $type...");

        $data = DB::table('product_variants')
            ->select("$column as entity_id", DB::raw('array_agg(id) as v_ids'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->get();

        $this->insertBatch($type, $data);
    }

    /**
     * Динамические свойства (Свойство ТОВАРА -> Все его варианты)
     */
    private function syncProperties(): void
    {
        $this->info("Обработка: prop_val (product_properties)...");

        $data = DB::table('product_properties')
            ->join('product_variants', 'product_variants.product_id', '=', 'product_properties.product_id')
            ->select('product_properties.property_value_id as entity_id', DB::raw('array_agg(DISTINCT product_variants.id) as v_ids'))
            ->groupBy('product_properties.property_value_id')
            ->get();

        $this->insertBatch('prop_val', $data);
    }

    private function insertBatch(string $type, $rows): void
    {
        foreach ($rows as $row) {
            DB::table('filter_vectors')->insert([
                'entity_type' => $type,
                'entity_id'   => $row->entity_id,
                'variant_ids' => $row->v_ids
            ]);
        }
    }
}
