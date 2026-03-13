<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowPostgreSqlTriggers extends Command
{
    protected $signature = 'app:show-db-triggers';
    protected $description = 'Запрос списка пользовательских триггеров в схеме public...';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info($this->description);

        // Выполняем SQL запрос
        $triggers = DB::select(self::getTriggerDataSql());

        if (empty($triggers)) {
            $this->warn('Триггеры не найдены.');

            return;
        }

        // Преобразуем объекты в массивы для метода table()
        $rows = collect($triggers)->map(fn($trigger) => [
            'Trigger Name' => $trigger->trigger_name,
            'Table Name' => $trigger->table_name,
            'Timing' => $trigger->timing,
            'Event' => $trigger->event,
        ]);

        // Выводим красивую таблицу
        $this->table(
            ['Trigger Name', 'Table Name', 'Timing', 'Event'],
            $rows
        );

        $successMessage = sprintf('Всего найдено: %s', count($triggers));
        $this->info($successMessage);
    }

    private static function getTriggerDataSql(): string
    {
        return <<<SQL
SELECT
    tgname AS trigger_name,
    relname AS table_name,
    CASE tgtype::integer & 66
        WHEN 2 THEN 'BEFORE'
        WHEN 64 THEN 'INSTEAD OF'
        ELSE 'AFTER'
        END AS timing,
    CASE tgtype::integer & 28
        WHEN 4 THEN 'INSERT'
        WHEN 8 THEN 'DELETE'
        WHEN 16 THEN 'UPDATE'
        WHEN 20 THEN 'INSERT/UPDATE'
        WHEN 28 THEN 'INSERT/UPDATE/DELETE'
        END AS event
FROM pg_trigger
         JOIN pg_class ON pg_trigger.tgrelid = pg_class.oid
         JOIN pg_namespace ON pg_class.relnamespace = pg_namespace.oid
WHERE tgisinternal = false
  AND nspname = 'public'
ORDER BY table_name, trigger_name;
SQL;
    }
}
