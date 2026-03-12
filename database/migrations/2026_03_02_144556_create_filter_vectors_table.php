<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS intarray');

        Schema::create('filter_vectors', function (Blueprint $table) {
            $table->id();
            // Тип сущности (brand, color, size, prop_val, category)
            $table->string('entity_type', 50);
            // ID сущности из справочников
            $table->integer('entity_id');

            // Поле для массива ID ВАРИАНТОВ (PostgreSQL специфично)
            // Мы создаем его через RAW SQL, так как стандартный Blueprint не всегда корректно ставит []
        });

        // Добавляем колонку variant_ids как массив целых чисел
        DB::statement('ALTER TABLE filter_vectors ADD COLUMN variant_ids int4[] NOT NULL DEFAULT \'{}\'');

        // ГЛАВНЫЙ ИНДЕКС: GIN (Generalized Inverted Index)
        // Именно он делает операцию пересечения (&) мгновенной на миллионах строк
        DB::statement('CREATE INDEX idx_fv_variant_ids ON filter_vectors USING GIN (variant_ids)');

        // Индекс для быстрой выборки всех свойств одного типа
        Schema::table('filter_vectors', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id']);
            // Уникальность, чтобы избежать дублей при пересборке индекса
            $table->unique(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_vectors');
    }
};
