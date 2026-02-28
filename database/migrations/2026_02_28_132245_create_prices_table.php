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
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_type_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('product_variant_id')
                ->constrained()
                ->onDelete('cascade');
            $table->decimal('price', 12)
                ->default(0);

            $table->unique(['product_variant_id', 'price_type_id'], 'variant_price_type_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
