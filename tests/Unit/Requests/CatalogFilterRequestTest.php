<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\CatalogFilterRequest;
use PHPUnit\Framework\Attributes\DataProvider;

class CatalogFilterRequestTest extends TestCase
{
    /**
     * Тест правил валидации (успешные случаи)
     */
    #[DataProvider('validDataProvider')]
    public function test_validation_passes_with_valid_data(array $data): void
    {
        $request = new CatalogFilterRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue(
            $validator->passes(),
            "Валидация должна была пройти: ".json_encode($validator->errors()->toArray())
        );
    }

    /**
     * Тест правил валидации (ошибочные случаи)
     */
    #[DataProvider('invalidDataProvider')]
    public function test_validation_fails_with_invalid_data(array $data, string $errorKey): void
    {
        $request = new CatalogFilterRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes(), "Валидация должна была упасть для: ".json_encode($data));
        $this->assertArrayHasKey($errorKey, $validator->errors()->toArray());
    }

    /**
     * Тест трансформации цены в prepareForValidation
     */
    public function test_prepare_for_validation_merges_price_correctly(): void
    {
        $data = [
            'filters' => [
                'price' => ['min' => '100', 'max' => '500'],
                'color' => [1, 2],
            ],
        ];

        $request = new CatalogFilterRequest($data);

        // В Laravel prepareForValidation вызывается автоматически перед валидацией
        // Мы можем вызвать его через Reflection или просто проверить результат через merge логику
        $request->setContainer(app())
            ->setRedirector(app(Redirector::class));

        $request->validateResolved();

        $price = $request->input('filters.price');

        $this->assertEquals(100.0, $price['min']);
        $this->assertEquals(500.0, $price['max']);
        $this->assertIsFloat($price['min']);
    }

    // --- Data Providers ---
    public static function validDataProvider(): array
    {
        return [
            'полный корректный запрос' => [
                [
                    'page' => 1,
                    'filters' => [
                        'price' => ['min' => 10, 'max' => 100],
                        'color' => [1, 5, 10],
                        'brand' => [2],
                    ],
                ],
            ],
            'минимальный запрос' => [['page' => 2]],
            'цена только с min' => [
                [
                    'filters' => ['price' => ['min' => 50]],
                ],
            ],
        ];
    }

    public static function invalidDataProvider(): array
    {
        return [
            'отрицательная страница' => [
                [
                    'page' => -1,
                ],
                'page',
            ],
            'max меньше min' => [
                [
                    'filters' => [
                        'price' => [
                            'min' => 100,
                            'max' => 50,
                        ],
                    ],
                ],
                'filters.price.max',
            ],
            'не числовой ID в фильтрах' => [
                [
                    'filters' => [
                        'color' => ['red', 1],
                    ],
                ],
                'filters.color.0',
            ],
            'ID меньше единицы' => [
                [
                    'filters' => [
                        'brand' => [0],
                    ],
                ],
                'filters.brand.0',
            ],
        ];
    }
}
