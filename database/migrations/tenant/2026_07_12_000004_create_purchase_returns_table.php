<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A return of received raw materials to a supplier. Editable while pending;
// "Complete" posts a purchase_return OUT per line from a chosen warehouse and
// marks it completed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Supplier::class)->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('purchase_returns');
    }
};
