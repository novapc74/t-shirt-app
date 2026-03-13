<?php

namespace App\Traits;

trait HumanSizeCounterTrait
{
    private const string TIME_PREFIX = 'Время выполнения скрипта - ';

    /**
     * Инициализирует метки времени и памяти для замера производительности.
     *
     * @return array{0: float, 1: int} [startTime, startMemory]
     */
    public static function initData(): array
    {
        return [self::getStartTime(), self::getStartMemory()];
    }

    /**
     * Возвращает сводную статистику производительности.
     *
     * @return array{execution_time: string, peak_memory: string, data_weight?: string}
     */
    public static function getScopeData(?int $startMemory = null, ?float $startTime = null): array
    {
        $stats = [
            'execution_time' => self::getExecutionTime($startTime),
            'peak_memory' => self::humanizeUsageMemory(true),
        ];

        if ($startMemory !== null) {
            $usedBytes = memory_get_usage() - $startMemory;
            $stats['data_weight'] = self::getHumanSize(max(0, $usedBytes));
        }

        return $stats;
    }

    private static function getHumanSize(mixed $data): string
    {
        $size = is_numeric($data) ? (float)$data : strlen((string)$data);

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }

    private static function getExecutionTime(?float $manualStart = null): string
    {
        $start = $manualStart ?? (defined('LARAVEL_START') ? LARAVEL_START : microtime(true));
        $diff = microtime(true) - $start;

        return round(abs($diff), 4).' c';
    }

    private static function getStartTime(): float
    {
        return microtime(true);
    }

    private static function getStartMemory(): int
    {
        return memory_get_usage();
    }

    private static function humanizeUsageMemory(bool $realUsage = false): string
    {
        return self::getHumanSize(memory_get_peak_usage($realUsage));
    }
}
