<?php

namespace Tests\Unit\Services;

use App\Services\Catalog\DTO\CatalogFilterRequestDto;
use DB;
use Mockery;
use Tests\TestCase;
use App\Services\Catalog\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FilterServiceTest extends TestCase
{
    use RefreshDatabase;

    private FilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FilterService();

        // Включаем расширение, если его нет (нужны права superuser в тестовой БД)
        DB::statement('CREATE EXTENSION IF NOT EXISTS intarray');

        $this->seedFilterVectors();
    }

    private function seedFilterVectors(): void
    {
        // Наличие: варианты 1, 2, 3, 4, 5 в стоке
        DB::table('filter_vectors')->insert([
            'entity_type' => 'system',
            'entity_id' => 1,
            'variant_ids' => '{1,2,3,4,5}',
        ]);

        // Бренды: Brand 10 (варианты 1, 2), Brand 11 (варианты 3, 4)
        DB::table('filter_vectors')->insert([
            ['entity_type' => 'brand', 'entity_id' => 10, 'variant_ids' => '{1,2}'],
            ['entity_type' => 'brand', 'entity_id' => 11, 'variant_ids' => '{3,4}'],
        ]);

        // Цвета: Color 20 (варианты 1, 3)
        DB::table('filter_vectors')->insert([
            'entity_type' => 'color',
            'entity_id' => 20,
            'variant_ids' => '{1,3}',
        ]);

        // Цены:
        // вариант 1 = 1000 руб, вариант 2 = 2000 руб,
        // вариант 3 = 3000 руб, вариант 4 = 4000 руб
        DB::table('filter_vectors')->insert([
            ['entity_type' => 'price_range', 'entity_id' => 1000, 'variant_ids' => '{1}'],
            ['entity_type' => 'price_range', 'entity_id' => 2000, 'variant_ids' => '{2}'],
            ['entity_type' => 'price_range', 'entity_id' => 3000, 'variant_ids' => '{3}'],
            ['entity_type' => 'price_range', 'entity_id' => 4000, 'variant_ids' => '{4}'],
        ]);
    }

    public function test_it_filters_by_brand_and_color(): void
    {
        // Выбираем бренд 10 (1,2) И цвет 20 (1,3) -> Должен остаться только 1
        $dto = new CatalogFilterRequestDto(filters: [
            'brand' => [10],
            'color' => [20],
        ]);

        $result = $this->service->getMatchedVariantIds($dto);

        $this->assertEquals([1], array_map('intval', $result));
    }

    public function test_it_filters_by_price_range(): void
    {
        // Цена от 2500 до 4500 -> Должны вернуться варианты 3 и 4
        $dto = new CatalogFilterRequestDto(filters: [
            'price' => ['min' => 2500, 'max' => 4500],
        ]);

        $result = $this->service->getMatchedVariantIds($dto);

        $this->assertCount(2, $result);
        $this->assertContains(3, array_map('intval', $result));
        $this->assertContains(4, array_map('intval', $result));
    }

    public function test_it_excludes_type_for_smart_counters(): void
    {
        // Если мы фильтруем по Brand 10 (1,2), но исключаем 'brand' (для счетчиков)
        // То должны вернуться все варианты, подходящие под остальные фильтры (здесь только сток)
        $dto = new CatalogFilterRequestDto(filters: [
            'brand' => [10],
        ]);

        $result = $this->service->getMatchedVariantIds($dto, excludeType: 'brand');

        // Вернутся все из system:1 {1,2,3,4,5}
        $this->assertCount(5, $result);
    }

    public function test_it_returns_empty_array_when_no_matches(): void
    {
        $dto = new CatalogFilterRequestDto([
            'brand' => [10],
            'price' => [
                'min' => 4000,
                'max' => 4000,
            ],
        ]);

        $this->assertEmpty($this->service->getMatchedVariantIds($dto));
    }

    public function test_mock_it_returns_empty_array_when_no_matches(): void
    {
        // Создаем мок и сразу описываем все ожидания цепочкой методов
        $dto = Mockery::mock(CatalogFilterRequestDto::class);
        $dto->allows([
            'getFilters'     => ['brand' => [10]],
            'hasPriceFilter' => true,
            'getMinPrice'    => '4000',
            'getMaxPrice'    => '4000',
        ]);

        $result = $this->service->getMatchedVariantIds($dto);

        $this->assertEmpty($result);
    }
}
