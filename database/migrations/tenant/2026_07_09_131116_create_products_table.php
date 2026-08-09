<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: products. The anchor entity later phases reference
// (sales order items, BOM, production). Only `sku` is unique; `barcode` is
// optional and not unique. category_id/supplier_id are nullOnDelete (fires on
// a real force-delete of the parent, not a soft delete).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignIdFor(Category::class)->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignIdFor(Supplier::class)->nullable()
                ->constrained()->nullOnDelete();
            $table->string('unit', 20);
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
