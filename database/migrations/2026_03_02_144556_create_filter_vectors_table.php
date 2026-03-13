<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Включаем расширение для работы с целочисленными массивами (обязательно для операторов &, |)
        DB::statement('CREATE EXTENSION IF NOT EXISTS intarray');

        // 2. Создаем структуру таблицы
        Schema::create('filter_vectors', function (Blueprint $table) {
            $table->id();
            // Тип сущности (brand, color, size, property_value, category, system)
            $table->string('entity_type', 50);
            // ID сущности из соответствующих справочников
            $table->integer('entity_id');

            /**
             * Уникальность пары гарантирует отсутствие дублей при пересборке фильтров
             * и автоматически создает эффективный B-tree индекс для поиска по типу и ID.
             */
            $table->unique(['entity_type', 'entity_id']);
        });

        // 3. Добавляем колонку variant_ids как массив int4 (через RAW SQL для точности типа)
        DB::statement('ALTER TABLE filter_vectors ADD COLUMN variant_ids int4[] NOT NULL DEFAULT \'{}\'');

        /**
         * 4. ГЛАВНЫЙ ИНДЕКС: GIN с операторным классом gin__int_ops.
         * Это критически важно: стандартный GIN на массивах работает медленнее,
         * чем этот специализированный под числа класс из расширения intarray.
         */
        DB::statement('CREATE INDEX idx_fv_variant_ids ON filter_vectors USING GIN (variant_ids gin__int_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_vectors');
        DB::statement('DROP EXTENSION IF EXISTS intarray');
    }
};
