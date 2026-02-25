<?php

namespace Database\Seeders;

use App\Models\Measure;
use App\Models\PriceType;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DirectorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Measure::create(['name' => 'Штука', 'symbol' => 'шт']);
        Measure::create(['name' => 'Килограмм', 'symbol' => 'кг']);

        PriceType::create(['name' => 'Розничная', 'slug' => 'retail']);

        Warehouse::create(['name' => 'Склад Москва']);
        Warehouse::create(['name' => 'Склад Санкт-Петербург']);
    }
}
