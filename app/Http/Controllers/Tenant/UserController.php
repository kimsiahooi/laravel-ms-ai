<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\UserData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\UserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController
{
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);
        $currentId = (int) $request->user()?->id;

        // Deactivated people stay listed (so an admin can reactivate them);
        // withoutGlobalScope(SoftDeletingScope) == withTrashed(). Active first.
        $query = User::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->with('roles')
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            // A null deleted_at sorts before any timestamp → active people first.
            ->orderBy('deleted_at');
        $sort = $this->applySort($query, $request, ['name', 'email', 'created_at']);

        $users = $this->paginateList($query, $perPage)
            ->through(fn (User $user): UserData => UserData::fromUser($user, $currentId));

        return Inertia::render('tenant/users/index', [
            'users' => $users,
            // Role names for the "assign a role" picker.
            'roles' => Role::orderBy('name')->pluck('name')->all(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort['sort'],
                'direction' => $sort['direction'],
            ],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);
        $user->syncRoles([$request->validated('role')]);

        $this->toast('User added — share their password so they can sign in.');

        return back();
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $role = (string) $request->validated('role');

        if ($this->wouldStrandUserAdmin($user, $role)) {
            $this->toast('There must always be someone who can manage users.', 'error');

            return back();
        }

        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ];
        // Blank password on edit = keep the current one.
        if (filled($request->validated('password'))) {
            $data['password'] = $request->validated('password');
        }
        $user->update($data);
        $user->syncRoles([$role]);

        $this->toast('User saved.');

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            $this->toast("You can't deactivate your own account.", 'error');

            return back();
        }

        if ($this->isLastUserAdmin($user)) {
            $this->toast('There must always be an active person who can manage users.', 'error');

            return back();
        }

        $user->delete();

        $this->toast("User deactivated — they can't sign in until you reactivate them.");

        return back();
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        $this->toast('User reactivated.');

        return back();
    }

    /** True when deactivating $user would leave nobody able to manage users. */
    private function isLastUserAdmin(User $user): bool
    {
        return $user->can('users.update')
            && User::permission('users.update')->count() <= 1;
    }

    /** True when changing $user to $newRole would strand the last user-admin. */
    private function wouldStrandUserAdmin(User $user, string $newRole): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        $newRoleManages = Role::where('name', $newRole)
            ->where('guard_name', 'web')
            ->first()?->hasPermissionTo('users.update') ?? false;

        return ! $newRoleManages && User::permission('users.update')->count() <= 1;
    }
}
