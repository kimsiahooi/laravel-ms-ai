<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Provision (or drop) the isolated E2E tenant + its database. MUST run with
 * `--env=e2e` so it targets the dedicated `..._e2e` central DB and the `e2e_tenant_`
 * database prefix — never the dev data. `up` is idempotent: it always clears any
 * prior e2e tenant first, then provisions a fresh one seeded with the demo dataset.
 */
class E2eTenant extends Command
{
    protected $signature = 'e2e:tenant {action : up|down}';

    protected $description = 'Provision or drop the isolated E2E tenant + its database (requires --env=e2e).';

    public function handle(): int
    {
        if (! app()->environment('e2e')) {
            $this->error('Refusing to run outside APP_ENV=e2e (got "'.app()->environment().'"). Pass --env=e2e.');

            return self::FAILURE;
        }

        $slug = 'e2e';
        $dbName = config('tenancy.database.prefix').$slug.config('tenancy.database.suffix');

        // Idempotent reset. The Tenant model soft-deletes, so a plain delete() would
        // leave the row occupying the `id` primary key and the re-provision below
        // would hit a duplicate. Hard-delete via the builder (which also bypasses the
        // DeleteDatabase event), then drop the tenant database ourselves.
        Tenant::withTrashed()->whereKey($slug)->forceDelete();
        DB::connection('central')->getPdo()->exec("DROP DATABASE IF EXISTS `{$dbName}`");

        if ($this->argument('action') === 'down') {
            $this->info("Dropped e2e tenant ({$dbName}).");

            return self::SUCCESS;
        }

        app(ProvisionTenant::class)->handle(
            name: 'E2E Workspace',
            slug: $slug,
            adminName: 'E2E Admin',
            adminEmail: 'e2e@example.test',
            adminPassword: 'password',
            seedDemoData: true,
        );

        $this->info("Provisioned e2e tenant + demo data ({$dbName}).");

        return self::SUCCESS;
    }
}
