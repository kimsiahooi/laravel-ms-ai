<?php

declare(strict_types=1);

use App\Models\StockTake;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One counted line of a stock take. `stockable_snapshot` captures name/sku/unit at
// count time. `system_qty` is the on-hand snapshot when the take started; variance
// = counted_qty − system_qty is what gets posted to the ledger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(StockTake::class)->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->json('stockable_snapshot');
            $table->decimal('system_qty', 15, 4);
            $table->decimal('counted_qty', 15, 4);
            $table->decimal('variance', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
