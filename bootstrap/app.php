<?php

use App\Exceptions\BlockedByDependentsException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route unauthenticated users to the login for their area rather than
        // Fortify's root /login. Tenant context is added in the tenant slice.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            if (tenant()) {
                return route('tenant.login', ['tenant' => tenant('id')]);
            }

            return route('home');
        });

        // And send already-authenticated users to their own area's dashboard.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            if (tenant()) {
                return route('tenant.dashboard', ['tenant' => tenant('id')]);
            }

            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A blocked delete (a location that still has warehouses, or a warehouse
        // holding stock) comes back as an error toast on the same page, not a 500.
        $exceptions->render(function (BlockedByDependentsException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        });

        // Friendly, branded pages for the statuses a person is most likely to hit:
        // a 403 from the permission gate (AuthorizeTenantRoute) and a 404 for a
        // missing page. Rendered through Inertia so they keep the app's look and an
        // Inertia visit swaps to them in place. Pure API/JSON clients still get the
        // default JSON error; 500s keep the default handler (local debugging +
        // production logging stay untouched).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! in_array($response->getStatusCode(), [403, 404], true)) {
                return $response;
            }

            $wantsJson = ($request->is('api/*') || $request->expectsJson())
                && ! $request->hasHeader('X-Inertia');
            if ($wantsJson) {
                return $response;
            }

            // Point "go back" at the right home for the area the error happened in,
            // so an admin or tenant user never dead-ends on the public welcome page.
            $tenant = tenant();
            $homeUrl = match (true) {
                $request->is('admin', 'admin/*') => $request->user('central')
                    ? route('admin.dashboard', [], false)
                    : route('admin.login', [], false),
                $tenant !== null => route('tenant.dashboard', ['tenant' => $tenant->getKey()], false),
                default => '/',
            };
            $homeLabel = match (true) {
                str_contains($homeUrl, '/login') => 'Back to sign in',
                $homeUrl === '/' => 'Back to home',
                default => 'Back to dashboard',
            };

            return Inertia::render('errors/error', [
                'status' => $response->getStatusCode(),
                'homeUrl' => $homeUrl,
                'homeLabel' => $homeLabel,
            ])->toResponse($request)->setStatusCode($response->getStatusCode());
        });
    })->create();
