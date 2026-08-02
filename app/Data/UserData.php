<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A tenant user for the Users list: identity, their role, and whether active. */
#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $role,
        public string $created_at,
        public bool $is_active,
        public bool $is_self,
    ) {}

    public static function fromUser(User $user, int $currentUserId): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->getRoleNames()->first(),
            created_at: $user->created_at?->toISOString() ?? '',
            // Soft-deleted = deactivated (can't sign in until restored).
            is_active: ! $user->trashed(),
            is_self: $user->id === $currentUserId,
        );
    }
}
