<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product category. A per-tenant catalog model — it lives on the default
 * connection, which InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class Category extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'description'];
}
