<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:web; belt-and-suspenders.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $ignoreId = $customer instanceof Customer ? $customer->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('customers', 'email')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
