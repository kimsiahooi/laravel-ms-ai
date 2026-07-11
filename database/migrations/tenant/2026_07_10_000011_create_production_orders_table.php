<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A production (manufacturing) order: make `quantity` of a product by consuming
// its bill of materials. Cancellable while pending; "Complete" posts a
// production_consume OUT per material and a production_output IN for the product
// into a chosen location, then marks it completed. `product_snapshot` keeps the
// order readable if the product is later edited or deleted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->decimal('quantity', 15, 4);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
