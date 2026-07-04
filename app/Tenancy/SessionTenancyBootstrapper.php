<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Scopes the session cookie name per tenant. On a single domain with path/slug
 * tenancy, DatabaseTenancyBootstrapper already routes DB sessions into each
 * tenant's own database; this additionally gives each tenant (and central) a
 * distinct cookie name so navigating tenant A -> tenant B does not overwrite the
 * cookie that referenced A's session. Together they fully isolate auth sessions.
 *
 * Runs on tenancy init AFTER DatabaseTenancyBootstrapper and — via middleware
 * priority (InitializeTenancyByPath before StartSession) — BEFORE the session
 * store/cookie is built.
 */
final class SessionTenancyBootstrapper implements TenancyBootstrapper
{
    private ?string $originalCookie = null;

    public function __construct(private readonly Repository $config) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalCookie = $this->config->get('session.cookie');

        $this->config->set(
            'session.cookie',
            $this->originalCookie.'_tenant_'.$tenant->getTenantKey(),
        );
    }

    public function revert(): void
    {
        if ($this->originalCookie !== null) {
            $this->config->set('session.cookie', $this->originalCookie);
            $this->originalCookie = null;
        }
    }
}
