<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\SupplierData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SupplierController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        return $this->resourceIndex(
            $request,
            Supplier::class,
            'tenant/suppliers/index',
            'suppliers',
            fn (Supplier $supplier): SupplierData => SupplierData::from($supplier),
        );
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        $this->toast('Supplier created.');

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        $this->toast('Supplier updated.');

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        $this->toast('Supplier deleted.');

        return back();
    }
}
