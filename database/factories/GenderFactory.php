<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GenderFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->randomElement(['Мужской', 'Женский', 'Детский', 'Унисекс']),
            'priority' => $this->faker->randomDigit(),
        ];
    }
}
