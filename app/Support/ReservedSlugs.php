<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Slugs that must never be resolved as a tenant by the /{tenant} path segment.
 *
 * Used to build the Route::pattern('tenant', ...) constraint (declared in
 * AppServiceProvider) so reserved / central words fall through to real central
 * routes or a normal 404 instead of hitting InitializeTenancyByPath.
 */
final class ReservedSlugs
{
    /** @var list<string> */
    public const LIST = [
        // tenancy / infra
        'admin', 'central', 'tenant',
        'api', 'sanctum', 'broadcasting', 'livewire', 'telescope', 'horizon',
        // health + build / static assets
        'up', 'storage', 'build', 'vendor', 'assets',
        'css', 'js', 'img', 'images', 'fonts', 'well-known',
        // auth (Fortify)
        'login', 'logout', 'register',
        'password', 'forgot-password', 'reset-password',
        'verify-email', 'email', 'user', 'two-factor-challenge',
        // app
        'dashboard', 'settings', 'home',
    ];

    /**
     * A regex for a SINGLE path segment, safe to drop into Route::pattern().
     *
     * The negative lookahead rejects an EXACT reserved word (a reserved word not
     * followed by another slug character, so `admin` is blocked but `administration`
     * still resolves), then requires a valid lowercase kebab slug. Deliberately no
     * `$` anchor: Symfony inlines this into the full-path regex, where `$` would
     * mean end-of-URL and break multi-segment tenant routes like /{tenant}/dashboard.
     */
    public static function pattern(): string
    {
        $reserved = implode('|', array_map('preg_quote', self::LIST));

        return '(?!(?:'.$reserved.')(?![a-z0-9-]))[a-z0-9](?:[a-z0-9-]*[a-z0-9])?';
    }
}
