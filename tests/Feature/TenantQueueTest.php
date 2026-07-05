<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('stores tenant-context database-queue jobs in the central jobs table', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    // Push onto the database queue while tenancy is active. The queue is pinned to
    // the central connection, so the job must land in central.jobs — not the tenant
    // DB (which has no jobs table, and which the central worker never reads).
    $tenant->run(function () {
        Queue::connection('database')->pushRaw(
            json_encode(['displayName' => 'probe']),
        );
    });

    expect(DB::connection('central')->table('jobs')->count())->toBe(1);
});
