<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: customers. Email is unique within the tenant (nullable —
// MySQL permits multiple NULLs), name is not unique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable()->unique();
            // Buyer tax identity + structured address for e-invoice (MyInvois /
            // InvoiceNow) — all optional; the export/labels adapt to MY vs SG.
            $table->string('tin', 100)->nullable();                 // Tax Identification Number
            $table->string('registration_no', 100)->nullable();     // SSM (MY) / UEN (SG)
            $table->string('sst_registration_no', 100)->nullable(); // SST/GST registration
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();                    // address line(s)
            $table->string('city', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('country_code', 2)->nullable();          // ISO-3166 alpha-2
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
