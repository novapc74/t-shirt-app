<?php

namespace Tests\Unit\Services\Catalog;

use Mockery;
use Tests\TestCase;
use Mockery\MockInterface;
use App\Services\Catalog\FilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use App\Repositories\CatalogRepository\FilterRepositoryInterface;

class FilterServiceTest extends TestCase
{
    private FilterService $service;
    private FilterRepositoryInterface|MockInterface $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryMock = Mockery::mock(FilterRepositoryInterface::class);
        $this->service = new FilterService($this->repositoryMock);
    }

    #[DataProvider('filterDataProvider')]
    public function test_get_matched_variant_ids_logic(
        array $inputFilters,
        ?string $excludeType,
        array $expectedFilters,
        array $expectedPriceRange
    ): void {
        $dto = new CatalogFilterRequestDto($inputFilters);

        $this->repositoryMock->shouldReceive('findMatchedVariantIds')->once()->with(
            $expectedFilters,
            $expectedPriceRange
        )->andReturn([1, 2, 3]);

        $result = $this->service->getMatchedVariantIds($dto, $excludeType);

        $this->assertEquals([1, 2, 3], $result);
    }

    public static function filterDataProvider(): array
    {
        return [
            'все фильтры активны' => [
                // Аргументы строго по порядку метода теста
                ['brand' => [1, 2], 'color' => [10], 'price' => ['min' => 100, 'max' => 500]], // $input
                null,                                                                         // $excludeType
                ['brand' => [1, 2], 'color' => [10]],                                         // $expectedFilters
                ['min' => 100, 'max' => 500],                                                 // $expectedPrice
            ],
            'исключение типа для счетчиков' => [
                ['brand' => [1, 2], 'color' => [10]],
                'brand',
                ['color' => [10]],
                [],
            ],
            'игнорирование цены при исключении цены' => [
                ['brand' => [1, 2], 'price' => ['min' => 100, 'max' => 500,],],
                'price',
                ['brand' => [1, 2]],
                [],
            ],
            'пустые фильтры' => [
                [],
                null,
                [],
                [],
            ],
            'только минимальная цена (дефолт макс)' => [
                ['price' => ['min' => 500, 'max' => null]],
                null,
                [],
                ['min' => 500, 'max' => 100000000],
            ],
            'только максимальная цена (дефолт мин)' => [
                ['price' => ['min' => null, 'max' => 800]],
                null,
                [],
                ['min' => 0, 'max' => 800],
            ],
        ];
    }

    #[DataProvider('priceExclusionProvider')]
    public function test_it_correctly_handles_price_exclusion_logic(
        array $inputFilters,
        ?string $excludeType,
        array $expectedPriceRange
    ): void {
        // 1. Создаем реальный DTO с данными
        $dto = new CatalogFilterRequestDto($inputFilters);

        // 2. Настраиваем ожидание для репозитория
        // Мы проверяем именно второй аргумент ($priceRange)
        $this->repositoryMock->shouldReceive('findMatchedVariantIds')
            ->once()
            ->with(Mockery::any(), $expectedPriceRange)
            ->andReturn([]);

        // 3. Вызываем сервис
        $this->service->getMatchedVariantIds($dto, $excludeType);

        $this->assertTrue(true); // Для PHPUnit, так как проверка в shouldReceive
    }

    public static function priceExclusionProvider(): array
    {
        return [
            'цена активна (не исключаем)' => [
                ['brand' => [1, 2], 'price' => ['min' => 100, 'max' => 500]], // $inputFilters
                null,                                                        // $excludeType
                ['min' => 100, 'max' => 500],                                // $expectedPriceRange
            ],
            'цена исключена (для счетчиков цены)' => [
                ['brand' => [1, 2], 'price' => ['min' => 100, 'max' => 500]], // $inputFilters
                'price',                                                     // $excludeType
                [],                                                          // $expectedPriceRange
            ],
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
