<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Super-admin. Pinned to the central connection (via CentralConnection) and
 * authenticated only through the `central` guard at /admin. Distinct from the
 * per-tenant App\Models\User, which lives in each tenant's database.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class CentralUser extends Authenticatable implements PasskeyUser
{
    use CentralConnection;
    use Notifiable;
    use PasskeyAuthenticatable;

    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
