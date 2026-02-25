<?php

namespace Database\Factories;

use App\Models\Price;
use App\Models\PriceType;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'price_type_id' => PriceType::factory(),
            'amount' => $this->faker->numberBetween(1, 100),
            'currency' => $this->faker->currencyCode(),
        ];
    }
}
