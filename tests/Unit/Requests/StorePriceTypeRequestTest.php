<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\StorePriceTypeRequest;
use App\Models\PriceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Validation\Validator as ValidationValidator;

class StorePriceTypeRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Эмуляция работы FormRequest
     */
    protected function validate(array $data): ValidationValidator
    {
        // Имитируем prepareForValidation
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $request = new StorePriceTypeRequest();
        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_data(): void
    {
        $validator = $this->validate([
            'name' => 'Оптовая цена',
            'slug' => 'opt-price'
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_it_automatically_generates_slug_if_missing(): void
    {
        $validator = $this->validate([
            'name' => 'Розничная цена'
        ]);

        $this->assertTrue($validator->passes());
        $this->assertEquals('roznicnaia-cena', $validator->getData()['slug']);
    }

    public function test_it_fails_if_name_is_not_unique(): void
    {
        PriceType::factory()->create(['name' => 'VIP']);

        $validator = $this->validate([
            'name' => 'VIP'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_it_fails_if_slug_is_not_unique(): void
    {
        PriceType::factory()->create([
            'name' => 'Test'
        ]);

        $validator = $this->validate([
            'name' => 'Another Name',
            'slug' => 'test'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }
}
