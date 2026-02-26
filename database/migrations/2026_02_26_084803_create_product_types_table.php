<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Таблица типов товаров (Худи, Футболки и т.д.)
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // 2. Таблица брендов (Nike, Adidas и т.д.)
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // 3. Добавляем колонки в существующую таблицу products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Индексы для фильтрации внутри категории
            $table->index(['category_id', 'product_type_id'], 'idx_products_cat_type');
            $table->index(['category_id', 'brand_id'], 'idx_products_cat_brand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_type_id']);
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['product_type_id', 'brand_id']);
        });

        Schema::dropIfExists('brands');
        Schema::dropIfExists('product_types');
    }
};
