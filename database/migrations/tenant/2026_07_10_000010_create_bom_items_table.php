<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A product's BOM: the raw materials + per-unit quantity needed to
// make one of the product. A production order explodes this (× order quantity)
// and snapshots it at creation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(RawMaterial::class)->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->unique(['product_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
