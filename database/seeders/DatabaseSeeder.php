<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DirectorySeeder::class,
            PropertySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
