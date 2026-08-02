<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A sales order: products sold to a customer. Editable while pending; "Fulfill"
// checks availability and posts a sales_fulfillment OUT per line from a chosen
// location (Phase-3 ledger), then marks it fulfilled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable();
            $table->foreignIdFor(Customer::class)->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('currency', 3)->default('USD');
            // Base-currency units per 1 unit of the order currency (1 when already
            // in base); converts foreign orders to the base currency in rollups.
            $table->decimal('exchange_rate', 15, 6)->default(1);
            // SST/GST rate (%) snapshotted at issue, applied to taxable lines.
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('expected_date')->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignIdFor(Warehouse::class, 'fulfilled_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
