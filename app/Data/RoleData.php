<?php

declare(strict_types=1);

namespace App\Data;

use Database\Seeders\RolesSeeder;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A role for the Roles list + editor: its permissions and how many people have it. */
#[TypeScript]
class RoleData extends Data
{
    /**
     * @param  array<int, string>  $permissions  the granted permission names
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $permissions,
        public bool $is_locked,
        public int $user_count,
    ) {}

    public static function fromRole(Role $role): self
    {
        return new self(
            id: (int) $role->id,
            name: $role->name,
            permissions: $role->permissions->pluck('name')->all(),
            // The built-in Administrator can't be edited or deleted (lockout safety).
            is_locked: $role->name === RolesSeeder::ADMIN_ROLE,
            user_count: $role->users()->count(),
        );
    }
}
