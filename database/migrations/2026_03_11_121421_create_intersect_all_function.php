<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Функция объединения (OR) - возвращает уникальные отсортированные ID
        DB::statement(
            "
            CREATE OR REPLACE FUNCTION array_cat_agg_func(anyarray, anyarray)
            RETURNS anyarray AS $$
            BEGIN
                IF $1 IS NULL OR $1 = '{}'::int4[] THEN RETURN $2; END IF;
                IF $2 IS NULL OR $2 = '{}'::int4[] THEN RETURN $1; END IF;
                RETURN uniq(sort(array_cat($1, $2)));
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        "
        );

        DB::statement(
            "
            CREATE OR REPLACE AGGREGATE array_cat_agg(anyarray) (
                SFUNC = array_cat_agg_func,
                STYPE = anyarray,
                INITCOND = '{}'
            );
        "
        );

        // 2. Функция пересечения (AND) - ГАРАНТИРУЕТ отсутствие NULL
        DB::statement(
            "
            CREATE OR REPLACE FUNCTION intersect_all_func(anyarray, anyarray)
            RETURNS anyarray AS $$
            BEGIN
                -- Если это начало цепочки (первый элемент), просто берем его
                IF $1 IS NULL THEN RETURN $2; END IF;
                -- Если второй элемент NULL (не должно быть), возвращаем первый
                IF $2 IS NULL THEN RETURN $1; END IF;

                -- Если ХОТЯ БЫ ОДИН массив пустой, результат пересечения ВСЕГДА {}
                IF $1 = '{}'::int4[] OR $2 = '{}'::int4[] THEN
                    RETURN '{}'::int4[];
                END IF;

                -- Выполняем пересечение intarray
                RETURN $1 & $2;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        "
        );

        DB::statement(
            "
            CREATE OR REPLACE AGGREGATE intersect_agg(anyarray) (
                SFUNC = intersect_all_func,
                STYPE = anyarray,
                INITCOND = NULL
            );
        "
        );
    }

    public function down(): void
    {
        DB::statement('DROP AGGREGATE IF EXISTS intersect_agg(anyarray)');
        DB::statement('DROP FUNCTION IF EXISTS intersect_all_func(anyarray, anyarray)');
        DB::statement('DROP AGGREGATE IF EXISTS array_cat_agg(anyarray)');
        DB::statement('DROP FUNCTION IF EXISTS array_cat_agg_func(anyarray, anyarray)');
    }
};
