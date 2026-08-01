<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supplier in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $tax_id
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read User|null $creator
 */
#[Fillable(['name', 'contact_person', 'email', 'tax_id', 'phone', 'address', 'notes'])]
class Supplier extends Model
{
    use RecordsActivity;
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'contact_person', 'email', 'notes'];
}
