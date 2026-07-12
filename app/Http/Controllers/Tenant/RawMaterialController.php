<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\RawMaterialData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\RawMaterialRequest;
use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class RawMaterialController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        return $this->resourceIndex(
            $request,
            RawMaterial::class,
            'tenant/raw-materials/index',
            'rawMaterials',
            fn (RawMaterial $rawMaterial): RawMaterialData => RawMaterialData::from($rawMaterial),
        );
    }

    public function store(RawMaterialRequest $request): RedirectResponse
    {
        RawMaterial::create($request->validated());

        $this->toast('Raw material created.');

        return back();
    }

    public function update(RawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->update($request->validated());

        $this->toast('Raw material updated.');

        return back();
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->delete();

        $this->toast('Raw material deleted.');

        return back();
    }
}
