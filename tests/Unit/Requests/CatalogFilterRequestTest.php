<?php

namespace Tests\Unit\Requests;

use Generator;
use Tests\TestCase;
use ReflectionClass;
use ReflectionException;
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
     *
     * @throws ReflectionException
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

        $reflection = new ReflectionClass(CatalogFilterRequest::class);
        $method = $reflection->getMethod('prepareForValidation');
        $method->invoke($request);

        $price = $request->input('filters.price');

        $this->assertEquals(100.0, $price['min']);
        $this->assertEquals(500.0, $price['max']);
        $this->assertIsFloat($price['min']);
    }

    public static function validDataProvider(): Generator
    {
        yield 'полный корректный запрос' => [
            [
                'page' => 1,
                'filters' => [
                    'price' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'color' => [
                        1,
                        5,
                        10,
                    ],
                    'brand' => [
                        2,
                    ],
                ],
            ],
        ];
        yield 'минимальный запрос' => [
            [
                'page' => 2,
            ],
        ];
        yield 'цена только с min' => [
            [
                'filters' => [
                    'price' => [
                        'min' => 50,
                    ],
                ],
            ],
        ];
    }

    public static function invalidDataProvider(): \Generator
    {
        yield 'отрицательная страница' => [
            [
                'page' => -1,
            ],
            'page',
        ];
        yield 'max меньше min' => [
            [
                'filters' => [
                    'price' => ['min' => 100, 'max' => 50],
                ],
            ],
            'filters.price.max',
        ];
        yield 'не числовой id в фильтрах' => [
            [
                'filters' => [
                    'color' => ['red', 1],
                ],
            ],
            'filters.color',
        ];
        yield 'id меньше единицы' => [
            [
                'filters' => [
                    'brand' => [0],
                ],
            ],
            'filters.brand',
        ];
        yield 'фильтр не массив' => [
            [
                'filters' => [
                    'brand' => 123,
                ],
            ],
            'filters.brand',
        ];
    }
}
