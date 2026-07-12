<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CompleteProductionOrder;
use App\Actions\CompletePurchaseReturn;
use App\Actions\CompleteSalesReturn;
use App\Actions\FulfillSalesOrder;
use App\Actions\PostStockTake;
use App\Actions\ReceivePurchaseOrder;
use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockTakeStatus;
use App\Models\BomItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\StockTake;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds a brand-new tenant with a realistic Malaysia + Singapore starter dataset so
 * the owner can see how every screen works before entering their own data. Runs
 * inside the tenant database (via ProvisionTenant when the "sample data" toggle is
 * on). Uses StockService + the completion actions so on-hand and the ledger stay
 * consistent, and signs the admin in for the duration so activity is attributed.
 */
class DemoTenantSeeder extends Seeder
{
    private StockService $stock;

    public function run(): void
    {
        $admin = User::query()->orderBy('id')->first();

        if ($admin === null) {
            return; // provisioning always creates the first user before seeding
        }

        $this->stock = app(StockService::class);

        // Attribute the seeded activity + user_id to the workspace's first admin.
        Auth::guard('web')->login($admin);

        try {
            $this->seed($admin);
        } finally {
            Auth::guard('web')->logout();
        }
    }

    private function seed(User $admin): void
    {
        // --- Catalog -------------------------------------------------------
        $electronics = Category::create(['name' => 'Electronics', 'description' => 'Finished electronic goods']);
        Category::create(['name' => 'Packaging', 'description' => 'Boxes and packaging materials']);
        Category::create(['name' => 'Metal stock', 'description' => 'Sheet metal and fasteners']);
        Category::create(['name' => 'Office supplies', 'description' => 'General office items']);

        $sinar = Supplier::create([
            'name' => 'Sinar Teknik Sdn Bhd', 'email' => 'sales@sinarteknik.com.my',
            'phone' => '+60 3-7845 1200', 'address' => '12 Jalan Perindustrian, Shah Alam, Selangor 40000',
        ]);
        $kimHeng = Supplier::create([
            'name' => 'Kim Heng Hardware Sdn Bhd', 'email' => 'orders@kimheng.com.my',
            'phone' => '+60 3-7955 8820', 'address' => '88 Jalan SS15/4, Subang Jaya, Selangor 47500',
        ]);
        Supplier::create([
            'name' => 'Lion City Components Pte Ltd', 'email' => 'sales@lioncitycomp.sg',
            'phone' => '+65 6220 4455', 'address' => '3 Tuas Avenue 8, Singapore 639234',
        ]);

        $bumiMesra = Customer::create([
            'name' => 'Bumi Mesra Retail Sdn Bhd', 'email' => 'hello@bumimesra.com.my',
            'phone' => '+60 3-2141 6600', 'address' => 'Lot 5, Jalan Bukit Bintang, Kuala Lumpur 55100',
        ]);
        Customer::create([
            'name' => 'Kedai Serbaneka Aman', 'email' => 'aman.store@gmail.com',
            'phone' => '+60 12-334 9080', 'address' => '23 Jalan Ampang, Kuala Lumpur 50450',
        ]);
        $marinaBay = Customer::create([
            'name' => 'Marina Bay Traders Pte Ltd', 'email' => 'procurement@marinabaytraders.sg',
            'phone' => '+65 6820 1199', 'address' => '10 Marina Boulevard, Singapore 018983',
        ]);

        $steel = RawMaterial::create(['name' => 'Steel sheet 1mm', 'sku' => 'RM-STEEL-1MM', 'unit' => 'kg']);
        $copper = RawMaterial::create(['name' => 'Copper wire 2.5mm', 'sku' => 'RM-CU-2.5', 'unit' => 'm']);
        $abs = RawMaterial::create(['name' => 'ABS plastic pellet', 'sku' => 'RM-ABS-PEL', 'unit' => 'kg']);
        $screw = RawMaterial::create(['name' => 'M4 screw', 'sku' => 'RM-M4-SCR', 'unit' => 'pcs']);

        $fan = Product::create([
            'name' => 'Desk fan 12-inch', 'sku' => 'FAN-12', 'barcode' => '9551234500018',
            'unit' => 'pcs', 'category_id' => $electronics->id, 'supplier_id' => $sinar->id,
            'description' => 'Table fan with 3 speeds',
        ]);
        $socket = Product::create([
            'name' => 'Power extension socket 4-way', 'sku' => 'PWR-EXT-4W', 'barcode' => '9551234500025',
            'unit' => 'pcs', 'category_id' => $electronics->id, 'supplier_id' => $sinar->id,
        ]);
        $box = Product::create([
            'name' => 'Storage box 20L', 'sku' => 'BOX-20L', 'barcode' => '9551234500032', 'unit' => 'pcs',
        ]);
        $cable = Product::create([
            'name' => 'USB-C cable 1m', 'sku' => 'USB-C-1M', 'barcode' => '9551234500049',
            'unit' => 'pcs', 'category_id' => $electronics->id,
        ]);

        // BOMs (the fan + socket are manufacturable; the box + cable are bought-in).
        $this->bom($fan, $steel, 0.5);
        $this->bom($fan, $copper, 3);
        $this->bom($fan, $screw, 8);
        $this->bom($socket, $abs, 0.3);
        $this->bom($socket, $copper, 2);
        $this->bom($socket, $screw, 6);

        // --- Sites + warehouses -------------------------------------------
        $klHq = Location::create(['name' => 'Kuala Lumpur HQ', 'code' => 'KL-HQ', 'address' => 'Menara Aman, Jalan Tun Razak, Kuala Lumpur 50400']);
        $selDc = Location::create(['name' => 'Selangor Distribution', 'code' => 'SEL-DC', 'address' => 'Warehouse Block C, Shah Alam, Selangor 40150']);
        $sgCtl = Location::create(['name' => 'Singapore Central', 'code' => 'SG-CTL', 'address' => '5 Changi North Way, Singapore 498771']);

        $main = Warehouse::create(['location_id' => $klHq->id, 'name' => 'Main Store', 'code' => 'WH-KL-01', 'address' => $klHq->address]);
        $overflow = Warehouse::create(['location_id' => $selDc->id, 'name' => 'Overflow', 'code' => 'WH-SEL-01', 'address' => $selDc->address]);
        $sgWarehouse = Warehouse::create(['location_id' => $sgCtl->id, 'name' => 'Singapore Warehouse', 'code' => 'WH-SG-01', 'address' => $sgCtl->address]);

        // Reorder levels at the main store.
        $this->reorder($main, $steel, 100);
        $this->reorder($main, $copper, 150);
        $this->reorder($main, $abs, 80);
        $this->reorder($main, $cable, 50);

        // --- Opening stock (StockService keeps on-hand + ledger in sync) ---
        $this->stock->setLevel($main, $steel, 500, $admin, 'Opening balance');
        $this->stock->setLevel($main, $copper, 800, $admin, 'Opening balance');
        $this->stock->setLevel($main, $abs, 300, $admin, 'Opening balance');
        $this->stock->setLevel($main, $screw, 5000, $admin, 'Opening balance');
        $this->stock->setLevel($main, $box, 120, $admin, 'Opening balance');
        $this->stock->setLevel($main, $cable, 200, $admin, 'Opening balance');
        $this->stock->setLevel($sgWarehouse, $cable, 60, $admin, 'Opening balance');
        $this->stock->setLevel($sgWarehouse, $box, 40, $admin, 'Opening balance');

        // Transfers between warehouses.
        $this->stock->transfer($main, $overflow, $steel, 50, $admin, 'Rebalance stock');
        $this->stock->transfer($main, $overflow, $screw, 500, $admin, 'Rebalance stock');
        $this->stock->transfer($main, $sgWarehouse, $cable, 30, $admin, 'Restock Singapore');

        // --- Purchasing (1 pending, 1 received) ----------------------------
        $po = PurchaseOrder::create(['supplier_id' => $sinar->id, 'currency' => 'MYR', 'status' => PurchaseOrderStatus::Pending, 'user_id' => $admin->id, 'notes' => 'Restock for next production run']);
        $this->poItem($po, $steel, 200, 3.50);
        $this->poItem($po, $copper, 300, 1.20);

        $poReceived = PurchaseOrder::create(['supplier_id' => $kimHeng->id, 'currency' => 'MYR', 'status' => PurchaseOrderStatus::Pending, 'user_id' => $admin->id]);
        $this->poItem($poReceived, $abs, 150, 4.00);
        $this->poItem($poReceived, $screw, 2000, 0.05);
        app(ReceivePurchaseOrder::class)->handle($poReceived, $main, $admin);

        // --- Manufacturing (1 pending, 1 completed) ------------------------
        $this->productionOrder($fan, 20, $admin);                       // pending
        $completedMo = $this->productionOrder($socket, 30, $admin);
        app(CompleteProductionOrder::class)->handle($completedMo, $main, $admin);

        // --- Sales (1 pending, 1 fulfilled) --------------------------------
        $so = SalesOrder::create(['customer_id' => $bumiMesra->id, 'currency' => 'MYR', 'status' => SalesOrderStatus::Pending, 'user_id' => $admin->id]);
        $this->soItem($so, $box, 10, 25.00);
        $this->soItem($so, $cable, 15, 12.00);

        $soFulfilled = SalesOrder::create(['customer_id' => $marinaBay->id, 'currency' => 'SGD', 'status' => SalesOrderStatus::Pending, 'user_id' => $admin->id]);
        $this->soItem($soFulfilled, $cable, 20, 6.50);
        app(FulfillSalesOrder::class)->handle($soFulfilled, $main, $admin);

        // --- Returns (1 pending + 1 completed each) ------------------------
        $pr = PurchaseReturn::create(['supplier_id' => $sinar->id, 'status' => ReturnStatus::Pending, 'user_id' => $admin->id, 'notes' => 'Damaged on arrival']);
        $pr->items()->create($this->rmLine($steel, 10));

        $prDone = PurchaseReturn::create(['supplier_id' => $kimHeng->id, 'status' => ReturnStatus::Pending, 'user_id' => $admin->id]);
        $prDone->items()->create($this->rmLine($copper, 20));
        app(CompletePurchaseReturn::class)->handle($prDone, $main, $admin);

        $sr = SalesReturn::create(['customer_id' => $bumiMesra->id, 'status' => ReturnStatus::Pending, 'user_id' => $admin->id, 'notes' => 'Customer changed their mind']);
        $sr->items()->create($this->productLine($box, 2));

        $srDone = SalesReturn::create(['customer_id' => $marinaBay->id, 'status' => ReturnStatus::Pending, 'user_id' => $admin->id]);
        $srDone->items()->create($this->productLine($cable, 5));
        app(CompleteSalesReturn::class)->handle($srDone, $main, $admin);

        // --- Stock takes (1 draft, 1 posted) -------------------------------
        $this->stockTake($main, $admin, post: false);
        $this->stockTake($main, $admin, post: true);
    }

    private function bom(Product $product, RawMaterial $rawMaterial, float $quantity): void
    {
        BomItem::create(['product_id' => $product->id, 'raw_material_id' => $rawMaterial->id, 'quantity' => $quantity]);
    }

    private function reorder(Warehouse $warehouse, Product|RawMaterial $stockable, float $min): void
    {
        WarehouseReorderLevel::create([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'min_stock' => $min,
        ]);
    }

    private function poItem(PurchaseOrder $po, RawMaterial $rm, float $qty, float $cost): void
    {
        $po->items()->create([
            'raw_material_id' => $rm->id,
            'raw_material_snapshot' => ['name' => $rm->name, 'sku' => $rm->sku, 'unit' => $rm->unit],
            'quantity' => $qty,
            'unit_cost' => $cost,
        ]);
    }

    private function soItem(SalesOrder $so, Product $product, float $qty, float $price): void
    {
        $so->items()->create([
            'product_id' => $product->id,
            'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit],
            'quantity' => $qty,
            'unit_price' => $price,
        ]);
    }

    /** @return array<string, mixed> */
    private function rmLine(RawMaterial $rm, float $qty): array
    {
        return [
            'raw_material_id' => $rm->id,
            'raw_material_snapshot' => ['name' => $rm->name, 'sku' => $rm->sku, 'unit' => $rm->unit],
            'quantity' => $qty,
        ];
    }

    /** @return array<string, mixed> */
    private function productLine(Product $product, float $qty): array
    {
        return [
            'product_id' => $product->id,
            'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit],
            'quantity' => $qty,
        ];
    }

    private function productionOrder(Product $product, float $qty, User $admin): ProductionOrder
    {
        $product->loadMissing('bomItems.rawMaterial');

        $order = ProductionOrder::create([
            'product_id' => $product->id,
            'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit],
            'quantity' => $qty,
            'user_id' => $admin->id,
            'status' => ProductionOrderStatus::Pending,
        ]);

        foreach ($product->bomItems as $item) {
            $order->items()->create([
                'raw_material_id' => $item->raw_material_id,
                'raw_material_snapshot' => [
                    'name' => $item->rawMaterial?->name ?? '',
                    'sku' => $item->rawMaterial?->sku ?? '',
                    'unit' => $item->rawMaterial?->unit ?? '',
                ],
                'quantity_per_unit' => $item->quantity,
                'quantity_required' => (float) $item->quantity * $qty,
            ]);
        }

        return $order;
    }

    /**
     * Snapshot the warehouse's current on-hand into a stock take. When posting, count
     * one item 1 unit short to show a difference and post the adjustment.
     */
    private function stockTake(Warehouse $warehouse, User $admin, bool $post): void
    {
        $take = StockTake::create([
            'warehouse_id' => $warehouse->id,
            'status' => StockTakeStatus::Draft,
            'user_id' => $admin->id,
        ]);

        $stocks = WarehouseStock::where('warehouse_id', $warehouse->id)->with('stockable')->get();

        foreach ($stocks as $stock) {
            $stockable = $stock->stockable;

            if ($stockable === null) {
                continue;
            }

            $take->items()->create([
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'stockable_snapshot' => [
                    'name' => $stockable->name,
                    'sku' => $stockable->sku ?? null,
                    'unit' => $stockable->unit ?? '',
                ],
                'system_qty' => (float) $stock->quantity,
                'counted_qty' => (float) $stock->quantity,
                'variance' => 0,
            ]);
        }

        if (! $post) {
            return;
        }

        $take->load('items');
        $first = true;
        $counts = $take->items->map(function ($item) use (&$first): array {
            // Count the first item one short so the posted take shows a difference.
            $counted = $first && (float) $item->counted_qty >= 1
                ? (float) $item->counted_qty - 1
                : (float) $item->counted_qty;
            $first = false;

            return ['id' => $item->id, 'counted_qty' => $counted];
        })->all();

        app(PostStockTake::class)->handle($take, $counts, $admin);
    }
}
