<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // The `id` IS the slug: a string primary key (e.g. "acme") that also
            // names the tenant database (tenancy.database.prefix + id -> `tenant_acme`).
            // Capped at 50 so `<prefix><id>` stays within MySQL's 64-char db-name limit.
            $table->string('id', 50)->primary();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->json('data')->nullable(); // stancl data overflow + tenancy_* internal keys
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
