<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-order exchange rate: base-currency units per 1 unit of the order's own
     * currency (1 when the order is already in the base currency). Lets foreign
     * orders roll up to the tenant base currency in reports/dashboard. Existing
     * rows default to 1 (treated as base-rate).
     */
    public function up(): void
    {
        foreach (['purchase_orders', 'sales_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency');
            });
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'sales_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('exchange_rate');
            });
        }
    }
};
