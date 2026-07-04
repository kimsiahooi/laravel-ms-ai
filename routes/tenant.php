<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant Routes (path-identified by slug)
|--------------------------------------------------------------------------
|
| Intentionally empty for now.
|
| This application identifies tenants by URL slug (`/{tenant}/…`), NOT by
| domain. The default domain-based scaffold (InitializeTenancyByDomain +
| PreventAccessFromCentralDomains) was removed — it collided with the central
| `/` route and called Tenant::domains() (no domains table in this design).
|
| Phase 1 · Task 7 replaces this with a path-based `/{tenant}` route group
| using a slug resolver, registered via bootstrap/app.php.
|
*/
