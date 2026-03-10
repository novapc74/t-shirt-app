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
        DB::statement('CREATE EXTENSION IF NOT EXISTS intarray');

        DB::statement("
        CREATE TABLE filter_vectors (
            entity_type varchar(32),
            entity_id integer,
            product_ids integer[],
            PRIMARY KEY (entity_type, entity_id)
        )
    ");

        DB::statement('CREATE INDEX idx_filter_vectors_ids ON filter_vectors USING gist (product_ids gist__int_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_vectors');
    }
};
