<?php

namespace Database\Seeders;

use App\Models\Measure;
use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kg = Measure::where('symbol', 'кг')->first();

        // Определяем приоритеты для групп свойств
        $items = [
            [
                'name' => 'Цвет',
                'slug' => 'color',
                'priority' => 10
            ],
            [
                'name' => 'Размер',
                'slug' => 'size',
                'priority' => 20
            ],
            [
                'name' => 'Обхват головы',
                'slug' => 'head_size',
                'priority' => 25
            ],
            [
                'name' => 'Пол',
                'slug' => 'gender',
                'priority' => 30
            ],
            [
                'name' => 'Вес',
                'slug' => 'weight',
                'priority' => 40,
                'measure_id' => $kg?->id
            ],
        ];

        foreach ($items as $item) {
            Property::updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }

}
