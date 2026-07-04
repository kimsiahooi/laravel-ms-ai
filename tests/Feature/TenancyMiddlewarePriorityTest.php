<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Session\Middleware\StartSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

it('runs InitializeTenancyByPath before StartSession in the middleware priority list', function () {
    // Tenancy must be initialized before the session starts, so that the DB
    // session handler + session cookie are built against the tenant context
    // (per-tenant session isolation, Task 9). This guards that ordering.
    $priority = app(Kernel::class)->getMiddlewarePriority();

    $initIndex = array_search(InitializeTenancyByPath::class, $priority, true);
    $startIndex = array_search(StartSession::class, $priority, true);

    expect($initIndex)->not->toBeFalse('InitializeTenancyByPath missing from priority list')
        ->and($startIndex)->not->toBeFalse('StartSession missing from priority list')
        ->and($initIndex)->toBeLessThan($startIndex);
});
