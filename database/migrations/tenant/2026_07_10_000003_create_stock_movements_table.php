<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Append-only stock ledger. Every on-hand mutation writes exactly one row here
// and is never updated or deleted (no soft-deletes). `quantity` is SIGNED:
// positive = in, negative = out. `stockable` is a morph (product / raw_material).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4);
            $table->string('reason', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
