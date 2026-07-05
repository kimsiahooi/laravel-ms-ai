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
     * The `id` is a slug-style string key (e.g. "acme") supplied at creation; it
     * doubles as the tenant database name suffix (prefix + id). stancl's base model
     * already keys on `id`, so no getTenantKeyName() override is needed — but with a
     * null id_generator, GeneratesIds would treat the key as auto-incrementing and
     * clobber it with lastInsertId, hence the overrides below.
     */
    protected $keyType = 'string';

    public function getIncrementing(): bool
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    /**
     * Attributes kept as real `tenants` table columns. Every other attribute
     * overflows into the json `data` column. The primary key (`id`) and
     * `deleted_at` MUST be listed so they write to real columns, not `data`.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'logo',
            'deleted_at',
        ];
    }
}
