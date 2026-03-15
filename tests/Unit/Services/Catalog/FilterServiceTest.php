<?php

namespace Tests\Unit\Services\Catalog;

use Mockery;
use Tests\TestCase;
use Mockery\MockInterface;
use App\Services\Catalog\FilterService;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use App\Repositories\CatalogRepository\FilterRepositoryInterface;

class FilterServiceTest extends TestCase
{
    private FilterService $service;
    private FilterRepositoryInterface|MockInterface $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем мок репозитория
        $this->repositoryMock = Mockery::mock(FilterRepositoryInterface::class);

        // Внедряем мок в сервис
        $this->service = new FilterService($this->repositoryMock);
    }

    public function test_it_passes_correct_data_to_repository_with_all_filters(): void
    {
        // 1. Подготовка DTO
        $dto = Mockery::mock(CatalogFilterRequestDto::class);
        $dto->shouldReceive('getFilters')->andReturn([
            'brand' => [1, 2],
            'color' => [10],
            'price' => ['min' => 100, 'max' => 500],
        ]);
        $dto->shouldReceive('hasPriceFilter')->andReturn(true);
        $dto->shouldReceive('getMinPrice')->andReturn('100');
        $dto->shouldReceive('getMaxPrice')->andReturn('500');

        // 2. Ожидаем, что репозиторий получит 'brand' и 'color', но НЕ 'price' в первом аргументе
        // А во втором аргументе получит корректный range
        $this->repositoryMock->shouldReceive('findMatchedVariantIds')->once()->with(
                ['brand' => [1, 2], 'color' => [10]], // filters
                ['min' => 100, 'max' => 500]           // priceRange
            )->andReturn([1, 2, 3]);

        $result = $this->service->getMatchedVariantIds($dto);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_it_excludes_specific_type_for_smart_counters(): void
    {
        $dto = new CatalogFilterRequestDto(filters: [
            'brand' => [10],
            'color' => [20],
        ]);

        $this->repositoryMock->shouldReceive('findMatchedVariantIds')
            ->once()
            ->with(['color' => [20]], []) // 'brand' должен быть исключен
            ->andReturn([1, 2]);

        $result = $this->service->getMatchedVariantIds($dto, 'brand');

        // ДОБАВЛЯЕМ ПРОВЕРКУ
        $this->assertEquals([1, 2], $result);
    }

    public function test_it_ignores_price_when_exclude_type_is_price(): void
    {
        $dto = new CatalogFilterRequestDto(filters: [
            'brand' => [10],
            'price' => ['min' => 100, 'max' => 500]
        ]);

        $this->repositoryMock->shouldReceive('findMatchedVariantIds')
            ->once()
            ->with(['brand' => [10]], []) // цена должна уйти пустой
            ->andReturn([1]);

        $result = $this->service->getMatchedVariantIds($dto, 'price');

        // ДОБАВЛЯЕМ ПРОВЕРКУ
        $this->assertCount(1, $result);
    }

    public function test_it_uses_empty_price_range_when_no_price_is_provided(): void
    {
        // Ситуация: фильтров цены нет вообще
        $dto = new CatalogFilterRequestDto(filters: []);

        $this->repositoryMock->shouldReceive('findMatchedVariantIds')
            ->once()
            ->with([], []) // Ожидаем пустые фильтры и пустой диапазон
            ->andReturn([]);

        $result = $this->service->getMatchedVariantIds($dto);

        $this->assertIsArray($result);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
