<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\SalesReturn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Line items of a sales return. `product_snapshot` captures name/sku/unit at write
// time so the return still reads correctly if the product later changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SalesReturn::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->decimal('quantity', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
