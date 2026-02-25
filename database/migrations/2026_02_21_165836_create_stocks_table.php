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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('warehouse_id')
                ->constrained()
                ->onDelete('cascade');
            $table->integer('quantity')
                ->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'warehouse_id']);
            $table->index(['product_variant_id', 'quantity'], 'idx_stocks_availability');
        });

        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stock_quantity_positive CHECK (quantity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
