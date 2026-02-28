<?php

namespace App\Console\Commands;

use App\Models\Color;
use App\Models\Gender;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexSmartFilter extends Command
{
    protected $signature = 'shop:reindex';
    protected $description = 'Пересобирает индекс умного фильтра';

    public function handle(): void
    {
        $this->info('Очистка старого индекса...');
        DB::table('smart_filter_index')->truncate();

        $variants = ProductVariant::with([
            'product.propertyValues',
            'prices',
            'stocks'
        ])->get();

        $this->info("Найдено вариаций: {$variants->count()}. Начинаем индексацию...");

        $dataToInsert = [];

        foreach ($variants as $variant) {
            $product = $variant->product;

            // 1. Собираем общие данные (базовая цена и суммарный сток)
            $price = $variant->prices->first()?->price ?? 0;
            $totalStock = $variant->stocks->sum('quantity');

            // 2. Системные свойства (Цвет, Размер, Гендер)
            $systemProps = [
                ['prop_id' => Color::SMART_FILTER_ID, 'val_id' => $variant->color_id],
                ['prop_id' => Size::SMART_FILTER_ID, 'val_id' => $variant->size_id],
                ['prop_id' => Gender::SMART_FILTER_ID, 'val_id' => $variant->gender_id],
            ];

            foreach ($systemProps as $prop) {
                if ($prop['val_id']) {
                    $dataToInsert[] = $this->formatRow($variant, $product, $prop['prop_id'], $prop['val_id'], $price, $totalStock);
                }
            }

            // 3. Дополнительные свойства из product_properties (Материал и т.д.)
            foreach ($product->propertyValues as $propValue) {
                $dataToInsert[] = $this->formatRow($variant, $product, $propValue->property_id, $propValue->id, $price, $totalStock);
            }

            // Вставляем пачками по 500 строк, чтобы не перегружать память
            if (count($dataToInsert) >= 500) {
                DB::table('smart_filter_index')->insert($dataToInsert);
                $dataToInsert = [];
            }
        }

        // Вставка остатков
        if (!empty($dataToInsert)) {
            DB::table('smart_filter_index')->insert($dataToInsert);
        }

        $this->info('Индексация успешно завершена!');
    }

    private function formatRow($variant, $product, $propId, $valId, $price, $stock): array
    {
        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'property_id' => $propId,
            'property_value_id' => $valId,
            'price' => $price,
            'stock' => $stock,
            'is_active' => (bool)$product->is_active,
        ];
    }
}
