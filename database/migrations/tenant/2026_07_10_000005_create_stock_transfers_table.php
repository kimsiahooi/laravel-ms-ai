<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A stock transfer document: moving one stockable from a source location to a
// destination. The actual on-hand effect is two rows in the stock_movements
// ledger (transfer_out + transfer_in), written atomically by StockService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Warehouse::class, 'from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignIdFor(Warehouse::class, 'to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4);
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
