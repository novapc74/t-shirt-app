<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = ProductVariant::query();

        $color = 'Черный';
        $size = 'XL';
        $gender = 'male';
        // Фильтрация по JSON атрибутам
        if ($color) {
            $query->where('attributes->color->name', $color);
        }
        if ($size) {
            $query->where('attributes->size', $size);
        }
        if ($gender) {
            $query->where('attributes->gender', $gender);
        }

        dd($query->toSql(), $query->getBindings());
        $variants = $query->get();

        foreach ($variants as $variant) {
            $this->line("Found SKU: {$variant->sku} (Color: {$variant->attributes['color']['name']})");
        }
    }
}
