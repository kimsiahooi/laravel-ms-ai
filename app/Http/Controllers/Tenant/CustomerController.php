<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\CustomerData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CustomerController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        return $this->resourceIndex(
            $request,
            Customer::class,
            'tenant/customers/index',
            'customers',
            fn (Customer $customer): CustomerData => CustomerData::from($customer),
        );
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        $this->toast('Customer created.');

        return back();
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        $this->toast('Customer updated.');

        return back();
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        $this->toast('Customer deleted.');

        return back();
    }
}
