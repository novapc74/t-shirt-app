<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
//    /**
//     * Run the migrations.
//     */
//    public function up(): void
//    {
//        Schema::create('insert_category_product_trigger', function (Blueprint $table) {
//            $table->id();
//            $table->timestamps();
//        });
//    }
//
//    /**
//     * Reverse the migrations.
//     */
//    public function down(): void
//    {
//        Schema::dropIfExists('insert_category_product_trigger');
//    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Функция для таблицы CATEGORIES
        DB::statement("
            CREATE OR REPLACE FUNCTION check_category_parent_integrity()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Если категория становится дочерней (parent_id не NULL)
                IF NEW.parent_id IS NOT NULL THEN
                    -- Проверяем, нет ли у будущего родителя товаров
                    IF EXISTS (SELECT 1 FROM products WHERE category_id = NEW.parent_id) THEN
                        RAISE EXCEPTION 'Нельзя создать подкатегорию: родительская категория уже содержит товары. Товары могут быть только в категориях-листьях.';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 2. Функция для таблицы PRODUCTS
        DB::statement("
            CREATE OR REPLACE FUNCTION check_product_category_integrity()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Проверяем, нет ли у выбранной категории подкатегорий
                IF EXISTS (SELECT 1 FROM categories WHERE parent_id = NEW.category_id) THEN
                    RAISE EXCEPTION 'Нельзя добавить товар в эту категорию: она имеет подкатегории. Товары могут быть только в категориях-листьях.';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 3. Создаем триггер для категорий
        DB::statement("
            CREATE TRIGGER trg_check_category_integrity
            BEFORE INSERT OR UPDATE OF parent_id ON categories
            FOR EACH ROW
            EXECUTE FUNCTION check_category_parent_integrity();
        ");

        // 4. Создаем триггер для товаров
        DB::statement("
            CREATE TRIGGER trg_check_product_integrity
            BEFORE INSERT OR UPDATE OF category_id ON products
            FOR EACH ROW
            EXECUTE FUNCTION check_product_category_integrity();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_check_product_integrity ON products");
        DB::statement("DROP FUNCTION IF EXISTS check_product_category_integrity()");

        DB::statement("DROP TRIGGER IF EXISTS trg_check_category_integrity ON categories");
        DB::statement("DROP FUNCTION IF EXISTS check_category_parent_integrity()");
    }
};
