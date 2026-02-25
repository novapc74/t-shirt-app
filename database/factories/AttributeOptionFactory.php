<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeOptionFactory extends Factory
{

    public function definition(): array
    {
        return [
            'type' => $this->faker->word(),
            'value' => $this->faker->word(),
            'label' => $this->faker->word(),
        ];
    }
}
