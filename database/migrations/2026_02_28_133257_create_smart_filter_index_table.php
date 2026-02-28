<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smart_filter_index', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('brand_id');

            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('property_value_id');

            $table->decimal('price', 12)->index();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(
                ['category_id', 'property_id', 'property_value_id', 'is_active', 'stock', 'price'],
                'idx_smart_lookup'
            );
            $table->index(['product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_filter_index');
    }
};

