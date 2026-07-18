<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Line items of a purchase order. `raw_material_snapshot` captures name/sku/unit at
// write time so the order still reads correctly if the raw material later changes
// or is deleted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseOrder::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(RawMaterial::class)->nullable()->constrained()->nullOnDelete();
            $table->json('raw_material_snapshot');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
