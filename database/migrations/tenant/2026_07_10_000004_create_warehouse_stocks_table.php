<?php

declare(strict_types=1);

use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Materialized on-hand per (warehouse, stockable). Derived entirely from the
// stock_movements ledger, but kept current so reads are a single row lookup.
// One row per warehouse+stockable, enforced by the compound unique key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
