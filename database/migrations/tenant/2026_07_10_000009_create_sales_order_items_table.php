<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Line items of a sales order. `product_snapshot` captures name/sku/unit at write
// time so the order reads correctly if the product later changes or is deleted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SalesOrder::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
