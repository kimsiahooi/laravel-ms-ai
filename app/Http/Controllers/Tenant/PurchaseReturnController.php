<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\CompletePurchaseReturn;
use App\Data\OptionData;
use App\Data\PurchaseReturnData;
use App\Enums\ReturnStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\PurchaseReturnRequest;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $returns = PurchaseReturn::query()
            ->with(['supplier', 'items'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PurchaseReturn $return): PurchaseReturnData => PurchaseReturnData::from($return));

        return Inertia::render('tenant/purchase-returns/index', [
            'returns' => $returns,
            'suppliers' => OptionData::collect(Supplier::orderBy('name')->get(['id', 'name'])),
            'rawMaterials' => OptionData::collect(RawMaterial::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(PurchaseReturn $purchaseReturn): Response
    {
        $purchaseReturn->load(['supplier', 'items']);

        return Inertia::render('tenant/purchase-returns/show', [
            'return' => PurchaseReturnData::from($purchaseReturn),
        ]);
    }

    public function store(PurchaseReturnRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $return = PurchaseReturn::create([
                'supplier_id' => $request->integer('supplier_id') ?: null,
                'notes' => $request->input('notes'),
                'user_id' => $request->user()?->id,
                'status' => ReturnStatus::Pending,
            ]);

            $this->syncItems($return, $request->array('items'));
        });

        $this->toast('Purchase return created.');

        return back();
    }

    public function update(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($purchaseReturn->status === ReturnStatus::Pending, 422);

        DB::transaction(function () use ($request, $purchaseReturn): void {
            $purchaseReturn->update([
                'supplier_id' => $request->integer('supplier_id') ?: null,
                'notes' => $request->input('notes'),
            ]);

            $purchaseReturn->items()->delete();
            $this->syncItems($purchaseReturn, $request->array('items'));
        });

        $this->toast('Purchase return updated.');

        return back();
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $purchaseReturn->delete();

        $this->toast('Purchase return deleted.');

        return back();
    }

    public function complete(Request $request, PurchaseReturn $purchaseReturn, CompletePurchaseReturn $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
        ]);

        try {
            $action->handle(
                $purchaseReturn,
                Warehouse::findOrFail($validated['warehouse_id']),
                $request->user(),
            );
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Not enough stock at this warehouse to return.',
            ]);
        }

        $this->toast('Purchase return completed.');

        return back();
    }

    public function cancel(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($purchaseReturn->status === ReturnStatus::Pending, 422);

        $purchaseReturn->update(['status' => ReturnStatus::Cancelled]);

        $this->toast('Purchase return cancelled.');

        return back();
    }

    /**
     * (Re)create the return's line items, snapshotting each raw material's name/sku/unit.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(PurchaseReturn $return, array $items): void
    {
        foreach ($items as $item) {
            $rawMaterial = RawMaterial::find($item['raw_material_id']);

            $return->items()->create([
                'raw_material_id' => $rawMaterial?->id,
                'raw_material_snapshot' => [
                    'name' => $rawMaterial?->name ?? '',
                    'sku' => $rawMaterial?->sku ?? '',
                    'unit' => $rawMaterial?->unit ?? '',
                ],
                'quantity' => $item['quantity'],
            ]);
        }
    }
}
