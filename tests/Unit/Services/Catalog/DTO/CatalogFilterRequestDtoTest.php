<?php

namespace Tests\Unit\Services\Catalog\DTO;

use Mockery;
use Tests\TestCase;
use App\Http\Requests\CatalogFilterRequest;
use App\Services\Catalog\DTO\CatalogFilterRequestDto;

class CatalogFilterRequestDtoTest extends TestCase
{
    public function test_it_filters_unallowed_keys(): void
    {
        $request = Mockery::mock(CatalogFilterRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'filters' => [
                'brand' => [1, 2],
                'hack' => 'drop table', // Должен быть удален
                'price' => ['min' => 100],
            ],
            'page' => 2,
        ]);

        $allowedKeys = ['brand', 'color'];

        $dto = CatalogFilterRequestDto::fromRequest($request, $allowedKeys);

        $this->assertArrayHasKey('brand', $dto->getFilters());
        $this->assertArrayHasKey('price', $dto->getFilters());
        $this->assertArrayNotHasKey('hack', $dto->getFilters());
        $this->assertEquals(2, $dto->getPage());
    }

    public function test_it_correctly_identifies_price_filter(): void
    {
        $dto = new CatalogFilterRequestDto(['price' => ['min' => 100]], 1);
        $this->assertTrue($dto->hasPriceFilter());

        $dtoEmpty = new CatalogFilterRequestDto([], 1);
        $this->assertFalse($dtoEmpty->hasPriceFilter());
    }
}

