<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Models\PriceType;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StorePriceTypeRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator as ValidationValidator;

class StorePriceTypeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function validate(array $data): ValidationValidator
    {
        $request = new StorePriceTypeRequest();
        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    /**
     * Успешная валидация корректных данных.
     */
    public function test_it_passes_with_valid_data(): void
    {
        $validator = $this->validate([
            'title' => 'Оптовая цена',
            'priority' => 10
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Ошибка, если название не уникально.
     */
    public function test_it_fails_if_title_is_not_unique(): void
    {
        // Создаем существующий тип цены
        PriceType::create(['title' => 'VIP', 'slug' => 'vip']);

        $validator = $this->validate([
            'title' => 'VIP'
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'Тип цены с таким названием уже существует',
            $validator->errors()->first('title')
        );
    }

    /**
     * Ошибка, если название отсутствует или слишком длинное.
     */
    public function test_it_fails_on_invalid_title(): void
    {
        // Пустое название
        $this->assertFalse($this->validate(['title' => ''])->passes());

        // Слишком длинное
        $this->assertFalse($this->validate(['title' => str_repeat('a', 256)])->passes());
    }

    /**
     * Ошибка при неверном приоритете.
     */
    #[DataProvider('invalidPriorityProvider')]
    public function test_it_fails_on_invalid_priority($priority, $expectedError): void
    {
        $validator = $this->validate([
            'title' => 'Цена',
            'priority' => $priority
        ]);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString($expectedError, $validator->errors()->first('priority'));
    }

    public static function invalidPriorityProvider(): array
    {
        return [
            'меньше 1' => [0, 'в интервале от 1 до 100'],
            'больше 100' => [101, 'в интервале от 1 до 100'],
            'не число' => ['abc', 'целым числом'],
        ];
    }
}
