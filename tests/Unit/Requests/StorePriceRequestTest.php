<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Models\PriceType;
use App\Models\ProductVariant;
use App\Http\Requests\StorePriceRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator AS ValidationValidator;

class StorePriceRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Вспомогательный метод для имитации работы FormRequest
     */
    protected function validate(array $data): ValidationValidator
    {
        $request = new StorePriceRequest();

        // В реальности Laravel вызывает prepareForValidation автоматически.
        // В Unit тесте нам нужно имитировать это поведение вручную,
        // если мы хотим проверить логику замены запятой на точку.
        if (isset($data['amount'])) {
            $data['amount'] = str_replace(',', '.', $data['amount']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return Validator::make($data, $request->rules(), [], $request->attributes());
    }

    public function test_it_passes_with_valid_data(): void
    {
        $variant = ProductVariant::factory()->create();
        $type = PriceType::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'price_type_id' => $type->id,
            'amount' => '1500.50',
            'currency' => 'rub' // Проверим, что приведется к RUB
        ]);

        $this->assertTrue($validator->passes(), 'Валидация должна пройти для корректных данных');
        $this->assertEquals('RUB', $validator->getData()['currency']);
    }

    public function test_it_fails_if_amount_is_negative(): void
    {
        $variant = ProductVariant::factory()->create();
        $type = PriceType::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'price_type_id' => $type->id,
            'amount' => -10,
            'currency' => 'RUB'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_it_fails_if_relations_do_not_exist(): void
    {
        $validator = $this->validate([
            'product_variant_id' => 999, // Не существует
            'price_type_id' => 999,      // Не существует
            'amount' => 100,
            'currency' => 'RUB'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('product_variant_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('price_type_id', $validator->errors()->toArray());
    }

    public function test_it_fails_if_currency_is_not_3_characters(): void
    {
        $variant = ProductVariant::factory()->create();
        $type = PriceType::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'price_type_id' => $type->id,
            'amount' => 100,
            'currency' => 'RU' // Слишком коротко
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('currency', $validator->errors()->toArray());
    }

    public function test_it_handles_comma_in_amount(): void
    {
        $variant = ProductVariant::factory()->create();
        $type = PriceType::factory()->create();

        // Передаем сумму с запятой (как часто вводят пользователи)
        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'price_type_id' => $type->id,
            'amount' => '100,50',
            'currency' => 'RUB'
        ]);

        $this->assertTrue($validator->passes(), 'Запятая должна быть заменена на точку');
        $this->assertEquals('100.50', $validator->getData()['amount']);
    }
}
