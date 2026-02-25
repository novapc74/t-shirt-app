<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Функция для проверки при добавлении ТОВАРА
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_before_insert_product()
            RETURNS TRIGGER AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM categories WHERE parent_id = NEW.category_id) THEN
                    RAISE EXCEPTION 'Нельзя добавить товар: у категории есть подкатегории (ID: %)', NEW.category_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 2. Функция для проверки при добавлении КАТЕГОРИИ
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_before_insert_category()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.parent_id IS NOT NULL AND EXISTS (SELECT 1 FROM products WHERE category_id = NEW.parent_id) THEN
                    RAISE EXCEPTION 'Нельзя создать подкатегорию: в родительской категории уже есть товары (ID: %)', NEW.parent_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 3. Создаем триггер на таблицу PRODUCTS
        DB::unprepared("
            CREATE TRIGGER trigger_product_category_check
            BEFORE INSERT OR UPDATE ON products
            FOR EACH ROW EXECUTE FUNCTION check_before_insert_product();
        ");

        // 4. Создаем триггер на таблицу CATEGORIES
        DB::unprepared("
            CREATE TRIGGER trigger_category_parent_check
            BEFORE INSERT OR UPDATE ON categories
            FOR EACH ROW EXECUTE FUNCTION check_before_insert_category();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем триггеры и функции при откате
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_product_category_check ON products");
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_category_parent_check ON categories");
        DB::unprepared("DROP FUNCTION IF EXISTS check_before_insert_product()");
        DB::unprepared("DROP FUNCTION IF EXISTS check_before_insert_category()");
    }
};
