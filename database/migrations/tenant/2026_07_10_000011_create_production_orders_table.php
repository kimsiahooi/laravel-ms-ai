<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A production (manufacturing) order: make `quantity` of a product by consuming
// its BOM. Cancellable while pending; "Complete" posts a
// production_consume OUT per material and a production_output IN for the product
// into a chosen location, then marks it completed. `product_snapshot` keeps the
// order readable if the product is later edited or deleted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->decimal('quantity', 15, 4);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignIdFor(Warehouse::class, 'completed_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
