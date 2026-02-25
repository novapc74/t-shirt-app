<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'property_id' => Property::factory(),
            'value' => $this->faker->randomFloat(),
        ];
    }
}
