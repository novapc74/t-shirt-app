<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('price_type_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->unique(['product_variant_id', 'price_type_id']);

            $table->index(['price_type_id', 'amount', 'product_variant_id'], 'idx_prices_lookup_sort');
            $table->index(['product_variant_id', 'price_type_id'], 'idx_prices_variant_type');
        });

        DB::statement('ALTER TABLE prices ADD CONSTRAINT price_amount_positive CHECK (amount >= 0)');
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
