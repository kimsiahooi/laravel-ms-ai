<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * What the stock currently sitting in the warehouses is worth, in the tenant's base
 * currency.
 *
 * There is no cost field on an item, so the cost basis is *derived from what was
 * actually paid*: a raw material is valued at the weighted-average unit cost of every
 * purchase order line ever received (converted to base currency by that order's
 * exchange rate), and a finished product at the rolled-up cost of the materials in
 * its bill of materials. Anything we can't derive a cost for — a material never
 * purchased, a product with no bill of materials or one whose materials were never
 * purchased — is left out of the total and reported as `unvalued` instead of being
 * silently counted as zero.
 */
class InventoryValuationService
{
    /**
     * Stock value across the live warehouses, plus how much of the stock that figure
     * actually covers so the UI can be honest about what is missing.
     *
     * @return object{value: float, valued: int, unvalued: int}
     */
    public function onHand(): object
    {
        $materialCosts = $this->rawMaterialUnitCosts();
        $productCosts = $this->productUnitCosts($materialCosts);

        $value = 0.0;
        $valued = 0;
        $unvalued = 0;

        foreach ($this->quantitiesByItem() as $row) {
            $cost = $row['type'] === 'product'
                ? ($productCosts[$row['id']] ?? null)
                : ($materialCosts[$row['id']] ?? null);

            if ($cost === null) {
                $unvalued++;

                continue;
            }

            $valued++;
            $value += $row['quantity'] * $cost;
        }

        return (object) ['value' => round($value, 2), 'valued' => $valued, 'unvalued' => $unvalued];
    }

    /**
     * Weighted-average unit cost per raw material, in base currency, over every
     * received purchase order line. Returns of stock don't re-price what's left, so
     * they're deliberately not netted off here.
     *
     * @return array<int, float>
     */
    public function rawMaterialUnitCosts(): array
    {
        return DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('po.status', PurchaseOrderStatus::Received->value)
            ->whereNull('po.deleted_at')
            ->whereNotNull('poi.raw_material_id')
            ->groupBy('poi.raw_material_id')
            ->havingRaw('SUM(poi.quantity) > 0')
            ->selectRaw('poi.raw_material_id as id, SUM(poi.quantity * poi.unit_cost * po.exchange_rate) / SUM(poi.quantity) as unit_cost')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => (float) $row->unit_cost])
            ->all();
    }

    /**
     * Rolled-up unit cost per product, from its bill of materials. A product is only
     * priced when *every* material in its bill has a cost — a partial roll-up would
     * quietly under-value the stock, so those products count as unvalued instead.
     *
     * @param  array<int, float>  $materialCosts
     * @return array<int, float>
     */
    public function productUnitCosts(array $materialCosts): array
    {
        $costs = [];
        $incomplete = [];

        $lines = DB::table('bom_items')
            ->join('products', 'products.id', '=', 'bom_items.product_id')
            ->whereNull('products.deleted_at')
            ->select(['bom_items.product_id', 'bom_items.raw_material_id', 'bom_items.quantity'])
            ->get();

        foreach ($lines as $line) {
            $productId = (int) $line->product_id;
            $unitCost = $materialCosts[(int) $line->raw_material_id] ?? null;

            if ($unitCost === null) {
                $incomplete[$productId] = true;

                continue;
            }

            $costs[$productId] = ($costs[$productId] ?? 0.0) + (float) $line->quantity * $unitCost;
        }

        return array_diff_key($costs, $incomplete);
    }

    /**
     * Quantity in stock per item, summed across live warehouses. Deleted items and
     * warehouses are excluded — their stock is no longer part of the business.
     *
     * @return list<array{type: string, id: int, quantity: float}>
     */
    private function quantitiesByItem(): array
    {
        return array_values(DB::table('warehouse_stocks as ws')
            ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
            ->leftJoin('products as p', function ($join): void {
                $join->on('p.id', '=', 'ws.stockable_id')->where('ws.stockable_type', '=', 'product');
            })
            ->leftJoin('raw_materials as rm', function ($join): void {
                $join->on('rm.id', '=', 'ws.stockable_id')->where('ws.stockable_type', '=', 'raw_material');
            })
            ->whereNull('w.deleted_at')
            // The item still exists: whichever side matched must not be soft-deleted.
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->where('ws.stockable_type', 'product')->whereNotNull('p.id')->whereNull('p.deleted_at');
                })->orWhere(function ($q): void {
                    $q->where('ws.stockable_type', 'raw_material')->whereNotNull('rm.id')->whereNull('rm.deleted_at');
                });
            })
            ->groupBy('ws.stockable_type', 'ws.stockable_id')
            ->havingRaw('SUM(ws.quantity) > 0')
            ->selectRaw('ws.stockable_type, ws.stockable_id, SUM(ws.quantity) as quantity')
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->stockable_type,
                'id' => (int) $row->stockable_id,
                'quantity' => (float) $row->quantity,
            ])
            ->all());
    }
}
