<?php

namespace Tests\Unit\Requests;

use App\Models\Category;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Property;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreProductVariantRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StoreProductVariantRequestTest extends TestCase
{
    use RefreshDatabase;

    private Property $colorProp;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        parent::setUp();

        $category = Category::create([
            'name' => 'Тестовая категория',
        ]);

        $this->colorProp = Property::create([
            'name' => 'Цвет',
            'slug' => 'color'
        ]);

        $this->product = Product::create([
            'name' => 'Тестовый товар',
            'category_id' => $category->id
        ]);
    }

    private function validate(array $data): bool
    {
        return $this->getValidator($data)->passes();
    }

    private function getValidator(array $data):  \Illuminate\Validation\Validator
    {
        $request = new StoreProductVariantRequest();

        $request->merge($data);

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_data(): void
    {
        $data = [
            'product_id' => $this->product->id,
            'sku'        => 'TEST-SKU-100',
            'properties' => [
                ['id' => $this->colorProp->id, 'value' => 'Красный']
            ]
        ];

        $this->assertTrue($this->validate($data));
    }

    public function test_it_fails_if_property_id_does_not_exist(): void
    {
        $data = [
            'product_id' => $this->product->id,
            'sku'        => 'SKU-1',
            'properties' => [
                ['id' => 999, 'value' => 'Ошибка'] // ID 999 не существует
            ]
        ];

        $validator = $this->getValidator($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('properties.0.id', $validator->errors()->messages());
    }

    public function test_it_fails_if_properties_array_is_empty(): void
    {
        $data = [
            'product_id' => $this->product->id,
            'sku'        => 'SKU-2',
            'properties' => [] // Минимум 1 элемент (min:1)
        ];

        $this->assertFalse($this->validate($data));
    }

    public function test_it_has_correct_dynamic_attributes_names(): void
    {
        $data = [
            'product_id' => $this->product->id,
            'sku'        => 'SKU-3',
            'properties' => [
                ['id' => $this->colorProp->id, 'value' => ''] // Пустое значение
            ]
        ];

        $validator = $this->getValidator($data);

        // Проверяем, что :attribute заменился на "значение (Цвет)"
        $message = $validator->errors()->first('properties.0.value');
        $this->assertStringContainsString('значение (Цвет)', $message);
    }
}
