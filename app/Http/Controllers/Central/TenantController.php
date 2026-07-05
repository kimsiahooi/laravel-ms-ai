<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\ProvisionTenant;
use App\Http\Requests\Central\StoreTenantRequest;
use Illuminate\Http\RedirectResponse;

class TenantController
{
    public function store(StoreTenantRequest $request, ProvisionTenant $provision): RedirectResponse
    {
        $data = $request->validated();

        $tenant = $provision->handle(
            name: $data['name'],
            slug: $data['slug'],
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['admin_password'],
        );

        return redirect()->route('admin.dashboard')
            ->with('success', "Tenant \"{$tenant->name}\" created — login at /{$tenant->getKey()}/login.");
    }
}
