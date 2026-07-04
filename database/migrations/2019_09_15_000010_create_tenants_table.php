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
            $table->id();                     // bigint auto-increment tenant key (not a UUID)
            $table->string('name');
            $table->string('slug')->unique(); // path identifier
            $table->string('logo')->nullable();
            $table->json('data')->nullable(); // stancl VirtualColumn overflow + tenancy_* internal keys
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
