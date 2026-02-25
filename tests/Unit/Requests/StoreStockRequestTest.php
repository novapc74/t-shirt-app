<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\StoreStockRequest;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreStockRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Вспомогательный метод для запуска валидации.
     */
    protected function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreStockRequest();

        $request->replace($data);

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_data(): void
    {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => 50
        ]);

        $this->assertTrue($validator->passes(), 'Валидация должна пройти для корректных данных.');
    }

    public function test_it_fails_if_quantity_is_negative(): void
    {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => -1 // Ошибка: отрицательное значение
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Количество не может быть отрицательным.', $validator->errors()->first('quantity'));
    }

    public function test_it_fails_if_warehouse_does_not_exist(): void
    {
        $variant = ProductVariant::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => 999, // Ошибка: склада нет
            'quantity'           => 10
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('warehouse_id', $validator->errors()->toArray());
    }

    public function test_it_fails_if_stock_record_already_exists_for_variant_on_warehouse(): void
    {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Имитируем существующую запись в БД
        DB::table('stocks')->insert([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => 10,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Пытаемся провалидировать такие же данные (дубликат)
        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => 20
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Для этого товара на данном складе уже заведена запись остатков.', $validator->errors()->first('warehouse_id'));
    }

    public function test_it_fails_if_quantity_is_not_an_integer(): void
    {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $validator = $this->validate([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => 10.5 // Ошибка: дробное число
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Количество должно быть целым числом.', $validator->errors()->first('quantity'));
    }
}

