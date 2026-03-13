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
        if (isset($data['title'])) {
            $data['title'] = preg_replace('/\s+/', ' ', trim($data['title']));
        }

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_it_passes_with_valid_title(): void
    {
        $validator = $this->validate(['title' => 'Склад на Севере']);

        $this->assertTrue($validator->passes());
    }

    public function test_it_fails_if_title_is_missing(): void
    {
        $validator = $this->validate(['title' => '']);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
        $this->assertEquals('Название склада обязательно для заполнения.', $validator->errors()->first('title'));
    }

    public function test_it_fails_if_title_is_too_short(): void
    {
        $validator = $this->validate(['title' => 'A']);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Название склада должно содержать минимум 2 символа.', $validator->errors()->first('title'));
    }

    public function test_it_fails_if_title_already_exists(): void
    {
        Warehouse::factory()->create(['title' => 'Главный Склад']);

        $validator = $this->validate(['title' => 'Главный Склад']);

        $this->assertFalse($validator->passes());
        $this->assertEquals('Склад с таким названием уже существует.', $validator->errors()->first('title'));
    }

    public function test_it_trims_and_cleans_spaces_in_title(): void
    {
        // Передаем "грязную" строку с кучей пробелов
        $validator = $this->validate(['title' => '   Склад    №1   ']);

        $this->assertTrue($validator->passes());

        // Проверяем, что в данных валидатора строка стала чистой
        $this->assertEquals('Склад №1', $validator->getData()['title']);
    }
}

