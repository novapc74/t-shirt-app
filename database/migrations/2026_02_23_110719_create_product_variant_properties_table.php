<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variant_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('value');
            $table->string('label')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['variant_id', 'property_id', 'value'], 'idx_pvp_smart_filter');
            $table->index(['property_id', 'value', 'variant_id'], 'idx_pvp_lookup_filter');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_properties');
    }
};
