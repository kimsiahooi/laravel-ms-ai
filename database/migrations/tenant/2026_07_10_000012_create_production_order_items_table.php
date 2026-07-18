<?php

declare(strict_types=1);

use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The exploded BOM for one production order. Snapshotted at creation
// so editing a product's BOM never mutates existing orders: `quantity_per_unit`
// is the per-unit need copied from the BOM, `quantity_required` is that times the
// order quantity (what "Complete" consumes). `raw_material_snapshot` captures
// name/sku/unit at write time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductionOrder::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(RawMaterial::class)->nullable()->constrained()->nullOnDelete();
            $table->json('raw_material_snapshot');
            $table->decimal('quantity_per_unit', 15, 4);
            $table->decimal('quantity_required', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
    }
};
