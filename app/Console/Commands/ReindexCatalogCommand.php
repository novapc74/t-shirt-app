<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReindexCatalogCommand extends Command
{
    protected $signature = 'catalog:reindex';
    protected $description = 'Пересчитывает поисковые векторы фильтров (с учетом цен и остатков) и индекс цен';

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->info('🚀 Начинаю пересчет индексов каталога (учитываю цены и остатки)...');

        DB::transaction(function () {
            // 1. Очистка векторов
            DB::statement("TRUNCATE filter_vectors;");

            // 2. Бренды (только те, у которых есть товары в наличии с ценой)
            $this->comment('📦 Обновляю векторы брендов...');
            DB::statement("
                INSERT INTO filter_vectors (entity_type, entity_id, product_ids)
                SELECT 'brand', p.brand_id, array_agg(DISTINCT p.id ORDER BY p.id)
                FROM products p
                JOIN product_variants pv ON pv.product_id = p.id
                JOIN prices pr ON pr.product_variant_id = pv.id
                JOIN stocks st ON st.product_variant_id = pv.id
                WHERE p.brand_id IS NOT NULL AND st.quantity > 0
                GROUP BY p.brand_id;
            ");

            // 3. Категории (только те, у которых есть товары в наличии с ценой)
            $this->comment('📂 Обновляю векторы категорий...');
            DB::statement("
                INSERT INTO filter_vectors (entity_type, entity_id, product_ids)
                SELECT 'category', p.category_id, array_agg(DISTINCT p.id ORDER BY p.id)
                FROM products p
                JOIN product_variants pv ON pv.product_id = p.id
                JOIN prices pr ON pr.product_variant_id = pv.id
                JOIN stocks st ON st.product_variant_id = pv.id
                WHERE st.quantity > 0
                GROUP BY p.category_id;
            ");

            // 4. Характеристики (только для товаров в наличии)
            $this->comment('⚙️ Обновляю векторы характеристик...');
            DB::statement("
                INSERT INTO filter_vectors (entity_type, entity_id, product_ids)
                SELECT 'prop_val', pp.property_value_id, array_agg(DISTINCT pp.product_id ORDER BY pp.product_id)
                FROM product_properties pp
                JOIN product_variants pv ON pv.product_id = pp.product_id
                JOIN prices pr ON pr.product_variant_id = pv.id
                JOIN stocks st ON st.product_variant_id = pv.id
                WHERE st.quantity > 0
                GROUP BY pp.property_value_id;
            ");

            // 5. Варианты: Цвет, Размер, Гендер (только если есть цена и остаток)
            $this->comment('🎨 Обновляю векторы вариантов (цвет, размер, гендер)...');
            $variantEntities = [
                'color' => 'color_id',
                'size' => 'size_id',
                'gender' => 'gender_id'
            ];

            foreach ($variantEntities as $type => $column) {
                DB::statement("
                    INSERT INTO filter_vectors (entity_type, entity_id, product_ids)
                    SELECT '$type', pv.$column, array_agg(DISTINCT pv.product_id ORDER BY pv.product_id)
                    FROM product_variants pv
                    JOIN prices pr ON pr.product_variant_id = pv.id
                    JOIN stocks st ON st.product_variant_id = pv.id
                    WHERE pv.$column IS NOT NULL AND st.quantity > 0
                    GROUP BY pv.$column;
                ");
            }

            // 6. Обновление индекса цен (только для товаров в наличии)
            $this->comment('💰 Обновляю индекс цен...');
            DB::statement("TRUNCATE product_price_index;");
            DB::statement("
                INSERT INTO product_price_index (product_id, min_price)
                SELECT pv.product_id, MIN(pr.price::numeric)
                FROM product_variants pv
                JOIN prices pr ON pr.product_variant_id = pv.id
                JOIN stocks st ON st.product_variant_id = pv.id
                WHERE st.quantity > 0
                GROUP BY pv.product_id;
            ");
        });

        $this->info('✅ Индексация успешно завершена! Теперь фильтры показывают только актуальное наличие.');
    }
}
