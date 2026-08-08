<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\FulfillSalesOrder;
use App\Data\EInvoiceReadinessData;
use App\Data\OptionData;
use App\Data\SalesOrderData;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\SalesOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Settings\BusinessSettings;
use App\Support\ActiveExists;
use App\Support\DocumentNumberGenerator;
use App\Support\EInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SalesOrderController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $query = SalesOrder::query()
            ->with(['customer', 'items'])
            ->search($search);
        $sort = $this->applySort($query, $request, ['id', 'status', 'created_at']);

        $baseCurrency = app(BusinessSettings::class)->baseCurrency();

        $orders = $this->paginateList($query, $perPage)
            ->through(fn (SalesOrder $order): SalesOrderData => SalesOrderData::fromSalesOrder($order, $baseCurrency));

        return Inertia::render('tenant/sales-orders/index', [
            'orders' => $orders,
            // The order form picks a currency + FX rate; new orders default to base.
            'baseCurrency' => $baseCurrency,
            'currencies' => BusinessSettings::currencies(),
            'customers' => OptionData::collect(Customer::orderBy('name')->get(['id', 'name'])),
            'products' => OptionData::collect(Product::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort['sort'],
                'direction' => $sort['direction'],
            ],
        ]);
    }

    public function show(Request $request, SalesOrder $salesOrder): Response
    {
        $salesOrder->load(['customer', 'items']);

        return Inertia::render('tenant/sales-orders/show', [
            'order' => SalesOrderData::from($salesOrder),
            'warehouses' => fn () => $this->stockWarehouseOptions(),
            'print' => $request->boolean('print'),
            // The e-invoice checklist — what's still missing before this order can
            // be filed. A closure so partial reloads don't recompute it.
            'eInvoice' => fn () => EInvoiceReadinessData::forSalesOrder(
                $salesOrder,
                app(BusinessSettings::class),
            ),
        ]);
    }

    /**
     * The order as a structured e-invoice payload (MyInvois / InvoiceNow shaped),
     * downloaded as JSON for the accounting package or Peppol access point to
     * submit. This is the integration hook — the app never files it itself.
     */
    public function eInvoice(SalesOrder $salesOrder, EInvoiceBuilder $builder): JsonResponse
    {
        $salesOrder->load(['customer', 'items']);

        // The number is user-entered free text (R08b): it can hold quotes, newlines,
        // and legitimately slashes (INV/2026/001 is an ordinary MY/SG format). Reduce
        // it to a filesystem-safe slug, then let Symfony build the header — raw
        // interpolation would let a number truncate the filename or inject a
        // second disposition parameter.
        $slug = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $salesOrder->number), '-');
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'e-invoice-'.($slug !== '' ? $slug : "SO-{$salesOrder->id}").'.json',
        );

        // Pretty-printed: a human pastes/inspects this far more often than a machine
        // streams it, and one invoice is small. PRESERVE_ZERO_FRACTION keeps money
        // decimal — a total of 270.0 must not serialise as the integer 270.
        return response()->json(
            $builder->build($salesOrder)->toArray(),
            200,
            ['Content-Disposition' => $disposition],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public function store(SalesOrderRequest $request, DocumentNumberGenerator $numbers): RedirectResponse
    {
        $currency = strtoupper((string) $request->input('currency'));
        $manualNumber = $request->input('number');

        DB::transaction(function () use ($request, $currency, $manualNumber, $numbers): void {
            $order = SalesOrder::create([
                'customer_id' => $request->integer('customer_id'),
                'currency' => $currency,
                'exchange_rate' => $this->exchangeRateFor($currency, $request->float('exchange_rate')),
                // Snapshot the tax rate at issue so a later settings change can't
                // silently re-tax an existing invoice.
                'tax_rate' => app(BusinessSettings::class)->taxRate(),
                // Manual number (R08b) wins; otherwise auto-generate from settings.
                'number' => filled($manualNumber) ? $manualNumber : $this->nextUniqueNumber($numbers),
                'notes' => $request->input('notes'),
                'expected_date' => $request->date('expected_date'),
                'user_id' => $request->user()?->id,
                'status' => SalesOrderStatus::Pending,
            ]);

            $this->syncItems($order, $request->array('items'));
        });

        $this->toast('Sales order created.');

        return back();
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->status === SalesOrderStatus::Pending, 422);

        $currency = strtoupper((string) $request->input('currency'));

        DB::transaction(function () use ($request, $salesOrder, $currency): void {
            $salesOrder->update([
                'customer_id' => $request->integer('customer_id'),
                'currency' => $currency,
                'exchange_rate' => $this->exchangeRateFor($currency, $request->float('exchange_rate')),
                // Re-snapshotted on edit: only a *pending* order is editable, and a
                // pending order isn't issued yet, so it re-prices at today's rate.
                // Fulfilled/cancelled orders 422 above and keep their issued rate.
                'tax_rate' => app(BusinessSettings::class)->taxRate(),
                'number' => $request->input('number'),
                'notes' => $request->input('notes'),
                'expected_date' => $request->date('expected_date'),
            ]);

            $salesOrder->items()->delete();
            $this->syncItems($salesOrder, $request->array('items'));
        });

        $this->toast('Sales order updated.');

        return back();
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        $salesOrder->delete();

        $this->toast('Sales order deleted.');

        return back();
    }

    public function fulfill(Request $request, SalesOrder $salesOrder, FulfillSalesOrder $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', ActiveExists::of('warehouses')],
        ]);

        try {
            $action->handle(
                $salesOrder,
                Warehouse::findOrFail($validated['warehouse_id']),
                $request->user(),
            );
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Not enough stock at this warehouse to fulfill the order.',
            ]);
        }

        $this->toast('Sales order fulfilled.');

        return back();
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->status === SalesOrderStatus::Pending, 422);

        $salesOrder->update(['status' => SalesOrderStatus::Cancelled]);

        $this->toast('Sales order cancelled.');

        return back();
    }

    /**
     * The rate to store: 1 for a base-currency order (or a missing/zero rate), else
     * the entered rate. The server is the source of truth for the base case.
     */
    private function exchangeRateFor(string $currency, float $rate): float
    {
        if ($currency === app(BusinessSettings::class)->baseCurrency()) {
            return 1.0;
        }

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * The next auto number that isn't already taken by a (manually-numbered) live
     * order — auto + manual numbers share the column, which has no DB unique index.
     * Each attempt advances the sequence, so this terminates immediately in practice.
     */
    private function nextUniqueNumber(DocumentNumberGenerator $numbers): string
    {
        do {
            $number = $this->documentNumber($numbers);
        } while (SalesOrder::where('number', $number)->exists());

        return $number;
    }

    /**
     * The next sales-order document number, from the settings prefix + numbering
     * reset rule + financial-year. Call inside the store transaction (row-locked).
     */
    private function documentNumber(DocumentNumberGenerator $numbers): string
    {
        $settings = app(BusinessSettings::class)->values();
        $prefix = (string) ($settings['sales_order_prefix'] ?? 'SO');
        $resetsYearly = (string) ($settings['number_reset'] ?? 'yearly') === 'yearly';
        $period = $resetsYearly
            ? $this->financialYearLabel((int) ($settings['financial_year_start_month'] ?? 1))
            : null;

        return $numbers->next('sales_order', $prefix, $period);
    }

    /** The label of the financial year the current date falls in (its start year). */
    private function financialYearLabel(int $startMonth): string
    {
        $now = Date::now();

        return (string) ($now->month >= $startMonth ? $now->year : $now->year - 1);
    }

    /**
     * (Re)create the order's line items, snapshotting each product's name/sku/unit.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(SalesOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            $order->items()->create([
                'product_id' => $product?->id,
                'product_snapshot' => Product::snapshotOf($product),
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                // Absent (older payload) → taxable; string "0"/"1" or bool both handled.
                'taxable' => filter_var($item['taxable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
