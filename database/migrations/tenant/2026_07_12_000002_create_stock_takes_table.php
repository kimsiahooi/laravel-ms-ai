<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A physical stock count for one warehouse. `draft` while counts are entered;
// "Post" writes a stock_take adjustment per line (variance = counted − system)
// into the ledger and marks it posted. Kept for a traceable count history.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
