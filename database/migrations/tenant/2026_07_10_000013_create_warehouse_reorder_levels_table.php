<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-(warehouse, stockable) reorder threshold. Reorder POLICY, kept separate
// from the StockService-owned warehouse_stocks LEDGER so a threshold can exist
// with no stock row. One row per warehouse+stockable (compound unique).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_reorder_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->timestamps();

            // Explicit short name: the auto-generated one exceeds MySQL's 64-char
            // identifier limit (table name is longer than warehouse_stocks').
            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id'], 'warehouse_reorder_levels_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_reorder_levels');
    }
};
