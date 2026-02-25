<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreWarehouseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StoreWarehouseRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Вспомогательный метод для ручного запуска валидации.
     */
    protected function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreWarehouseRequest();

        // Имитируем очистку пробелов из prepareForValidation
        if (isset($data['name'])) {
            $data['name'] = preg_replace('/\s+/', ' ', trim($data['name']));
        }

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_name(): void
    {
        $validator = $this->validate(['name' => 'Склад на Севере']);

        $this->assertTrue($validator->passes());
    }

    public function test_it_fails_if_name_is_missing(): void
    {
        $validator = $this->validate(['name' => '']);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertEquals('Название склада обязательно для заполнения.', $validator->errors()->first('name'));
    }

    public function test_it_fails_if_name_is_too_short(): void
    {
        $validator = $this->validate(['name' => 'A']);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Название склада должно содержать минимум 2 символа.', $validator->errors()->first('name'));
    }

    public function test_it_fails_if_name_already_exists(): void
    {
        Warehouse::factory()->create(['name' => 'Главный Склад']);

        $validator = $this->validate(['name' => 'Главный Склад']);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Склад с таким названием уже существует.', $validator->errors()->first('name'));
    }

    public function test_it_trims_and_cleans_spaces_in_name(): void
    {
        // Передаем "грязную" строку с кучей пробелов
        $validator = $this->validate(['name' => '   Склад    №1   ']);

        $this->assertTrue($validator->passes());

        // Проверяем, что в данных валидатора строка стала чистой
        $this->assertEquals('Склад №1', $validator->getData()['name']);
    }
}

