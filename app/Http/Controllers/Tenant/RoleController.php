<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\RoleData;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\RoleRequest;
use App\Support\TenantPermissions;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController
{
    use RespondsWithToast;

    public function index(): Response
    {
        // A tenant has only a handful of roles, so this list isn't paginated.
        return Inertia::render('tenant/roles/index', [
            'roles' => RoleData::collect(
                Role::with('permissions')->orderBy('name')->get(),
            ),
            // The full permission catalog, grouped + plain-labelled, for the editor.
            'permissionGroups' => TenantPermissions::matrix(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->safe()->array('permissions'));

        $this->toast('Role saved.');

        return back();
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        // The built-in Administrator is locked so a tenant can't lock itself out.
        abort_if($role->name === RolesSeeder::ADMIN_ROLE, 403);

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->safe()->array('permissions'));

        $this->toast('Role saved.');

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === RolesSeeder::ADMIN_ROLE) {
            $this->toast("The Administrator role can't be deleted.", 'error');

            return back();
        }

        if ($role->users()->exists()) {
            $this->toast("Reassign this role's people before deleting it.", 'error');

            return back();
        }

        $role->delete();

        $this->toast('Role deleted.');

        return back();
    }
}
