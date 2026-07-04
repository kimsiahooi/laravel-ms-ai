<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    // Base Tenant does not ship database management; multi-DB mode needs this.
    use HasDatabase;
    use SoftDeletes;

    /**
     * Attributes kept as real `tenants` table columns. Every other attribute
     * overflows into the json `data` column (stancl VirtualColumn). `deleted_at`
     * MUST be listed so SoftDeletes writes/clears the real column, not `data`.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'logo',
            'deleted_at',
        ];
    }
}
