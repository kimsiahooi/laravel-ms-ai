<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A purchase order: raw materials ordered from a supplier. Editable while pending;
// "Receive" posts a purchase_receipt IN per line into a chosen location (Phase-3
// ledger) and marks it received.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Supplier::class)->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignIdFor(Warehouse::class, 'received_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
