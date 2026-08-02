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
 * Customer in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $tin
 * @property string|null $registration_no
 * @property string|null $sst_registration_no
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postcode
 * @property string|null $state_code
 * @property string|null $country_code
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read User|null $creator
 */
#[Fillable([
    'name', 'contact_person', 'email',
    'tin', 'registration_no', 'sst_registration_no',
    'phone', 'address', 'city', 'postcode', 'state_code', 'country_code', 'notes',
])]
class Customer extends Model
{
    use RecordsActivity;
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'contact_person', 'email', 'tin', 'registration_no', 'notes'];
}
