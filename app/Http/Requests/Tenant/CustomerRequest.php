<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Customer;
use Illuminate\Validation\Rule;

class CustomerRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $ignoreId = $customer instanceof Customer ? $customer->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('customers', 'email')->ignore($ignoreId),
            ],
            // Buyer tax identity + structured address for e-invoice (all optional).
            'tin' => ['nullable', 'string', 'max:100'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'sst_registration_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
