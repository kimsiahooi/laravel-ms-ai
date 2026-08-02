<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant counters for auto document numbers (e.g. SO-2026-0001). One row per
// (type, period); `period` is the financial-year label, or '' when numbering never
// resets. Incremented under a row lock so numbers are gap-free + unique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50);
            $table->string('period', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
