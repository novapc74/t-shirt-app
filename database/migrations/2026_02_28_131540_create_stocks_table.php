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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('warehouse_id')
                ->index()
                ->constrained()
                ->onDelete('cascade');
            $table->unsignedInteger('quantity')
                ->default(0);

            $table->unique(['product_variant_id', 'warehouse_id'], 'variant_warehouse_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
