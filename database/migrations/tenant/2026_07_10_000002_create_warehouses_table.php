<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant warehouse: a stock-holding building that belongs to a location.
// Code is unique within the tenant (nullable). Deleting the parent location is
// blocked in app code while warehouses remain; restrictOnDelete backstops any
// hard delete.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
