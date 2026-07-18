<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant key-value settings, grouped by category so different settings groups
// (business profile now; invoicing, notifications, … later) never mix. Each field is
// its own row. The unique (category, key) makes writes race-safe via updateOrCreate,
// and reads never create rows. Field metadata (type/label/options/validation) lives in
// code (App\Settings\*); this table stores only values.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['category', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
