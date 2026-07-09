<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Heal from any crashed prior run that leaked test tenant databases.
        $this->dropLeftoverTenantDatabases();
    }

    protected function tearDown(): void
    {
        // A test HTTP request through the tenant route group leaves tenancy
        // initialized (the default DB connection still points at the tenant DB).
        // Revert to central BEFORE dropping tenant DBs, otherwise the sweep and
        // RefreshDatabase's rollback would run against a just-dropped database.
        if ($this->app && tenant() !== null) {
            tenancy()->end();
        }

        $this->purgeTenants();

        parent::tearDown();
    }

    /**
     * Clean up every tenant a test created. Tenant creation runs CREATE DATABASE
     * (DDL), which implicitly commits and defeats RefreshDatabase's wrapping
     * transaction, so central `tenants` rows are freed explicitly (rather than
     * rolled back) and the separate tenant databases are dropped.
     */
    protected function purgeTenants(): void
    {
        if (! $this->app || ! $this->app->bound('db')) {
            return;
        }

        // Free slugs via a mass query-builder delete (no model events / no DDL).
        if (class_exists(Tenant::class)) {
            // Remove any file uploads left in each test tenant's suffixed
            // storage dir (storage/tenant<key>). Scoped to tenants in the TEST
            // central DB, so dev/prod tenant storage is never touched.
            $suffixBase = (string) config('tenancy.filesystem.suffix_base', 'tenant');
            foreach (Tenant::withTrashed()->pluck('id') as $key) {
                File::deleteDirectory(storage_path($suffixBase.$key));
            }

            Tenant::withTrashed()->forceDelete();
        }

        $this->dropLeftoverTenantDatabases();
    }

    /**
     * DROP every database using this project's dedicated TEST tenant prefix,
     * except the central test schema. Hard-guarded to a `msai_test_tenant_*`
     * prefix so it can never touch dev/prod tenant DBs (prefix `tenant`) or any
     * other project sharing this MySQL server.
     */
    protected function dropLeftoverTenantDatabases(): void
    {
        if (! $this->app || ! $this->app->bound('db')) {
            return;
        }

        $prefix = (string) config('tenancy.database.prefix');

        // Safety: only ever sweep the dedicated test prefix.
        if (! str_starts_with($prefix, 'msai_test_tenant')) {
            return;
        }

        $connection = DB::connection('central');
        $centralDb = (string) config('database.connections.central.database');

        foreach ($connection->select('SHOW DATABASES') as $row) {
            $name = (string) (array_values((array) $row)[0] ?? '');

            if ($name !== '' && $name !== $centralDb && str_starts_with($name, $prefix)) {
                $connection->statement("DROP DATABASE IF EXISTS `{$name}`");
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
