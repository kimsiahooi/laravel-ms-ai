<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController
{
    public function create(): Response
    {
        return Inertia::render('tenant/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // The default connection is the tenant DB here (InitializeTenancyByPath
        // already ran), so this authenticates against the tenant's own users table.
        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // The resolver forgot the {tenant} param, so supply the slug explicitly.
        return redirect()->intended(
            route('tenant.dashboard', ['tenant' => tenant('slug')])
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $slug = tenant('slug');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login', ['tenant' => $slug]);
    }
}
