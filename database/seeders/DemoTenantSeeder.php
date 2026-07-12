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
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds a brand-new tenant with a fuller Malaysia + Singapore starter dataset so the
 * owner can see how every screen behaves before entering their own data — ~20 rows per
 * list, at least one row of every status, and dates spread across the last ~90 days so
 * newest-first sorting and the dashboard charts show real variation. Runs inside the
 * tenant database (via ProvisionTenant when the "sample data" toggle is on), routing all
 * stock through StockService + the completion actions so on-hand + the ledger stay
 * consistent, and signing the admin in for the duration so activity is attributed.
 */
class DemoTenantSeeder extends Seeder
{
    private const CATEGORY_NAMES = [
        'Electronics', 'Packaging', 'Metal stock', 'Office supplies',
        'Tools & hardware', 'Cables & wiring', 'Cleaning supplies', 'Safety equipment',
    ];

    /** Sites: [name, code, region] — region drives the address pool. */
    private const SITES = [
        ['Kuala Lumpur HQ', 'KL-HQ', 'KL'],
        ['Selangor Distribution', 'SEL-DC', 'SEL'],
        ['Penang Branch', 'PNG-BR', 'PNG'],
        ['Johor Bahru Depot', 'JB-DP', 'JB'],
        ['Singapore Central', 'SG-CTL', 'SG'],
    ];

    /** @var array<string, list<string>> street lines per region (block number prepended). */
    private const STREETS = [
        'KL' => ['Jalan Tun Razak, Kuala Lumpur 50400', 'Jalan Ampang, Kuala Lumpur 50450', 'Jalan Bukit Bintang, Kuala Lumpur 55100'],
        'SEL' => ['Jalan Perindustrian, Shah Alam, Selangor 40000', 'Jalan SS15/4, Subang Jaya, Selangor 47500', 'Persiaran Kerjaya, Klang, Selangor 41200'],
        'PNG' => ['Lorong Industri, Bayan Lepas, Penang 11900', 'Jalan Perusahaan, Prai, Penang 13600'],
        'JB' => ['Jalan Tebrau, Johor Bahru, Johor 80300', 'Jalan Perindustrian Senai, Johor 81400'],
        'SG' => ['Tuas Avenue 8, Singapore 639234', 'Changi North Way, Singapore 498771', 'Ang Mo Kio Industrial Park, Singapore 569139', 'Marina Boulevard, Singapore 018983'],
    ];

    /** Supplier stems: [name stem, 'MY'|'SG']. Suffix + phone + address assembled below. */
    private const SUPPLIER_STEMS = [
        ['Sinar Teknik', 'MY'], ['Kim Heng Hardware', 'MY'], ['Maju Jaya Supplies', 'MY'],
        ['Global Metal Works', 'MY'], ['Bina Logam', 'MY'], ['Cahaya Elektrik', 'MY'],
        ['Perkasa Tooling', 'MY'], ['Seri Mutiara Trading', 'MY'], ['Utara Components', 'MY'],
        ['Damai Packaging', 'MY'], ['Warisan Industri', 'MY'], ['Zenith Fasteners', 'MY'],
        ['Harmoni Plastik', 'MY'], ['Teguh Steel', 'MY'],
        ['Lion City Components', 'SG'], ['Marina Metalworks', 'SG'], ['Orchard Electronics', 'SG'],
        ['Sentosa Supplies', 'SG'], ['Raffles Hardware', 'SG'], ['Jurong Fasteners', 'SG'],
    ];

    /** Customer stems: [name stem, 'MY'|'SG', 'Sdn Bhd'|'Pte Ltd'|'']. */
    private const CUSTOMER_STEMS = [
        ['Bumi Mesra Retail', 'MY', 'Sdn Bhd'], ['Kedai Serbaneka Aman', 'MY', ''],
        ['Metro Runcit', 'MY', 'Sdn Bhd'], ['Gemilang Stores', 'MY', 'Sdn Bhd'],
        ['Pasaraya Harmoni', 'MY', ''], ['Sri Impian Trading', 'MY', 'Sdn Bhd'],
        ['Kedai Elektrik Jaya', 'MY', ''], ['Nusantara Mart', 'MY', 'Sdn Bhd'],
        ['Restoran Selera Kita', 'MY', ''], ['Bengkel Auto Cergas', 'MY', ''],
        ['Kilang Roti Maju', 'MY', 'Sdn Bhd'], ['Koperasi Warga', 'MY', ''],
        ['Delima Furniture', 'MY', 'Sdn Bhd'], ['Fajar Hardware', 'MY', 'Sdn Bhd'],
        ['Marina Bay Traders', 'SG', 'Pte Ltd'], ['Katong Provisions', 'SG', 'Pte Ltd'],
        ['Tampines Retail', 'SG', 'Pte Ltd'], ['Clarke Quay Supplies', 'SG', 'Pte Ltd'],
        ['Bugis Trading', 'SG', 'Pte Ltd'], ['Woodlands Mart', 'SG', 'Pte Ltd'],
    ];

    /** Extra raw materials beyond the manufacturable core: [name, sku, unit]. */
    private const RAW_MATERIAL_POOL = [
        ['Aluminium sheet 2mm', 'RM-ALU-2MM', 'kg'], ['PVC pipe 20mm', 'RM-PVC-20', 'm'],
        ['Rubber gasket', 'RM-RUB-GSK', 'pcs'], ['Tempered glass panel', 'RM-GLS-PNL', 'pcs'],
        ['Enamel paint', 'RM-PNT-ENL', 'L'], ['Solder wire 0.8mm', 'RM-SLD-08', 'm'],
        ['Hex nut M4', 'RM-NUT-M4', 'pcs'], ['Flat washer M4', 'RM-WSH-M4', 'pcs'],
        ['Ball bearing 608', 'RM-BRG-608', 'pcs'], ['Coil spring 20mm', 'RM-SPR-20', 'pcs'],
        ['Epoxy adhesive', 'RM-ADH-EPX', 'L'], ['EVA foam sheet', 'RM-FOM-EVA', 'pcs'],
        ['Label roll 40mm', 'RM-LBL-40', 'roll'], ['Corrugated cardboard', 'RM-CBD-COR', 'pcs'],
        ['Packing tape', 'RM-TPE-PCK', 'roll'], ['Hex bolt M6', 'RM-BLT-M6', 'pcs'],
    ];

    /** Extra bought-in products beyond the manufacturable core: [name, sku, unit]. */
    private const PRODUCT_POOL = [
        ['LED desk lamp', 'LMP-LED', 'pcs'], ['USB wall charger 20W', 'CHG-20W', 'pcs'],
        ['Power bank 10000mAh', 'PWB-10K', 'pcs'], ['HDMI cable 2m', 'HDMI-2M', 'pcs'],
        ['Travel adapter', 'ADP-TRV', 'pcs'], ['Wall bracket', 'BRK-WAL', 'pcs'],
        ['Steel shelf 900mm', 'SHF-900', 'pcs'], ['Plastic container 10L', 'CNT-10L', 'pcs'],
        ['Cable organiser tray', 'TRY-CBL', 'pcs'], ['Binder clip pack', 'CLP-BND', 'pack'],
        ['Phone holder', 'HLD-PHN', 'pcs'], ['Laptop stand', 'STD-LAP', 'pcs'],
        ['USB hub 4-port', 'HUB-4P', 'pcs'], ['HDMI splitter', 'SPL-HDMI', 'pcs'],
        ['Desk mat large', 'MAT-DSK', 'pcs'], ['Surge protector', 'SRG-PRO', 'pcs'],
    ];

    private const CURRENCIES = ['MYR', 'SGD'];

    private StockService $stock;

    private User $admin;

    /** @var list<Warehouse> */
    private array $warehouses = [];

    private Warehouse $mainWarehouse;

    /** @var list<Supplier> */
    private array $suppliers = [];

    /** @var list<Customer> */
    private array $customers = [];

    /** @var list<RawMaterial> */
    private array $rawMaterials = [];

    /** @var list<Product> */
    private array $products = [];

    /** @var list<Product> products that carry a BOM (manufacturable) */
    private array $manufacturable = [];

    public function run(): void
    {
        $admin = User::query()->orderBy('id')->first();

        if ($admin === null) {
            return; // provisioning always creates the first user before seeding
        }

        $this->admin = $admin;
        $this->stock = app(StockService::class);

        // Attribute the seeded activity + user_id to the workspace's first admin.
        Auth::guard('web')->login($admin);

        try {
            $this->seedCatalog();
            $this->seedSites();
            $this->seedOpeningStock();
            $this->seedTransfers();
            $this->seedPurchaseOrders();
            $this->seedProductionOrders();
            $this->seedSalesOrders();
            $this->seedPurchaseReturns();
            $this->seedSalesReturns();
            $this->seedStockTakes();
        } finally {
            Auth::guard('web')->logout();
        }
    }

    // -- Date + stamping helpers -------------------------------------------------

    /** A random moment within the last $maxDaysAgo days (the app uses CarbonImmutable). */
    private function randomDate(int $maxDaysAgo = 88): CarbonInterface
    {
        return now()
            ->subDays(random_int(1, $maxDaysAgo))
            ->subHours(random_int(0, 23))
            ->subMinutes(random_int(0, 59));
    }

    /**
     * $count spread-out moments within the last ~$maxDaysAgo days, sorted oldest-first.
     * Stamping a table's rows in creation order with these makes created_at rise with the
     * id — so a newest-first list (created_at DESC, id DESC) reads as a clean descending
     * run of order numbers instead of a shuffle, while the dates still span the window.
     *
     * @return list<CarbonInterface>
     */
    private function spreadDates(int $count, int $maxDaysAgo = 88): array
    {
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = $this->randomDate($maxDaysAgo);
        }

        usort($dates, fn (CarbonInterface $a, CarbonInterface $b): int => $a->getTimestamp() <=> $b->getTimestamp());

        return $dates;
    }

    /**
     * Backdate a model's timestamps (and any extra date columns) without firing model
     * events. forceFill bypasses guarded columns; saveQuietly skips the activity log.
     *
     * @param  array<string, mixed>  $extra
     */
    private function stamp(Model $model, CarbonInterface $when, array $extra = []): void
    {
        $model->forceFill(['created_at' => $when, 'updated_at' => $when, ...$extra])->saveQuietly();
    }

    /**
     * Run a stock-mutating callback, then backdate every stock movement + transfer it
     * created to $when — so the movements + Sales-vs-Purchases charts spread out instead
     * of clustering at seed time. Sequential seeding makes the id floor reliable.
     */
    private function atDate(CarbonInterface $when, callable $callback): mixed
    {
        $movementFloor = (int) (StockMovement::max('id') ?? 0);
        $transferFloor = (int) (StockTransfer::max('id') ?? 0);

        $result = $callback();

        StockMovement::where('id', '>', $movementFloor)->update(['created_at' => $when, 'updated_at' => $when]);
        StockTransfer::where('id', '>', $transferFloor)->update(['created_at' => $when, 'updated_at' => $when]);

        return $result;
    }

    private function myPhone(): string
    {
        return sprintf('+60 3-%d %04d', random_int(7000, 9999), random_int(0, 9999));
    }

    private function sgPhone(): string
    {
        return sprintf('+65 6%03d %04d', random_int(200, 999), random_int(0, 9999));
    }

    private function address(string $region): string
    {
        $streets = self::STREETS[$region];

        return random_int(1, 120).' '.$streets[array_rand($streets)];
    }

    private function slug(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($name)) ?? 'demo';
    }

    // -- Catalog -----------------------------------------------------------------

    private function seedCatalog(): void
    {
        $categories = [];
        foreach (self::CATEGORY_NAMES as $name) {
            $categories[] = Category::create(['name' => $name, 'description' => $name.' items']);
        }

        foreach (self::SUPPLIER_STEMS as [$stem, $region]) {
            $name = $stem.($region === 'SG' ? ' Pte Ltd' : ' Sdn Bhd');
            $tld = $region === 'SG' ? 'sg' : 'com.my';
            $this->suppliers[] = Supplier::create([
                'name' => $name,
                'email' => 'sales@'.$this->slug($stem).'.'.$tld,
                'phone' => $region === 'SG' ? $this->sgPhone() : $this->myPhone(),
                'address' => $this->address($region === 'SG' ? 'SG' : ['KL', 'SEL', 'PNG', 'JB'][array_rand(['KL', 'SEL', 'PNG', 'JB'])]),
            ]);
        }

        foreach (self::CUSTOMER_STEMS as [$stem, $region, $suffix]) {
            $name = trim($stem.' '.$suffix);
            $this->customers[] = Customer::create([
                'name' => $name,
                'email' => 'hello@'.$this->slug($stem).'.'.($region === 'SG' ? 'sg' : 'com.my'),
                'phone' => $region === 'SG' ? $this->sgPhone() : $this->myPhone(),
                'address' => $this->address($region === 'SG' ? 'SG' : ['KL', 'SEL'][array_rand(['KL', 'SEL'])]),
            ]);
        }

        // Manufacturable core (kept coherent so production orders explode a real BOM).
        $steel = RawMaterial::create(['name' => 'Steel sheet 1mm', 'sku' => 'RM-STEEL-1MM', 'unit' => 'kg']);
        $copper = RawMaterial::create(['name' => 'Copper wire 2.5mm', 'sku' => 'RM-CU-2.5', 'unit' => 'm']);
        $abs = RawMaterial::create(['name' => 'ABS plastic pellet', 'sku' => 'RM-ABS-PEL', 'unit' => 'kg']);
        $screw = RawMaterial::create(['name' => 'M4 screw', 'sku' => 'RM-M4-SCR', 'unit' => 'pcs']);
        $this->rawMaterials = [$steel, $copper, $abs, $screw];
        foreach (self::RAW_MATERIAL_POOL as [$name, $sku, $unit]) {
            $this->rawMaterials[] = RawMaterial::create(['name' => $name, 'sku' => $sku, 'unit' => $unit]);
        }

        $electronics = $categories[0];
        $fan = Product::create(['name' => 'Desk fan 12-inch', 'sku' => 'FAN-12', 'barcode' => '9551234500018', 'unit' => 'pcs', 'category_id' => $electronics->id, 'supplier_id' => $this->suppliers[0]->id, 'description' => 'Table fan with 3 speeds']);
        $socket = Product::create(['name' => 'Power extension socket 4-way', 'sku' => 'PWR-EXT-4W', 'barcode' => '9551234500025', 'unit' => 'pcs', 'category_id' => $electronics->id, 'supplier_id' => $this->suppliers[0]->id]);
        $this->products = [$fan, $socket];
        $this->manufacturable = [$fan, $socket];

        foreach (self::PRODUCT_POOL as $i => [$name, $sku, $unit]) {
            $this->products[] = Product::create([
                'name' => $name, 'sku' => $sku, 'unit' => $unit,
                'barcode' => (string) (9551234500030 + $i),
                'category_id' => $categories[array_rand($categories)]->id,
                'supplier_id' => $this->suppliers[array_rand($this->suppliers)]->id,
            ]);
        }

        // BOMs for the manufacturable core.
        $this->bom($fan, $steel, 0.5);
        $this->bom($fan, $copper, 3);
        $this->bom($fan, $screw, 8);
        $this->bom($socket, $abs, 0.3);
        $this->bom($socket, $copper, 2);
        $this->bom($socket, $screw, 6);
    }

    // -- Sites, reorder levels, opening stock ------------------------------------

    private function seedSites(): void
    {
        foreach (self::SITES as [$name, $code, $region]) {
            $location = Location::create(['name' => $name, 'code' => $code, 'address' => $this->address($region)]);
            $this->warehouses[] = Warehouse::create([
                'location_id' => $location->id,
                'name' => $name.' Store',
                'code' => 'WH-'.$code,
                'address' => $location->address,
            ]);
        }
        $this->mainWarehouse = $this->warehouses[0];

        // Reorder levels at the main store: a mix, some ABOVE opening stock so the
        // dashboard's low-stock KPI + the Reports low-stock list are non-empty.
        foreach ($this->rawMaterials as $i => $rm) {
            if ($i % 2 === 0) {
                $this->reorder($this->mainWarehouse, $rm, $i < 4 ? 100 : random_int(50, 400));
            }
        }
        foreach ($this->products as $i => $product) {
            if ($i % 3 === 0) {
                // Every 6th product's threshold sits above its opening stock (low alert).
                $this->reorder($this->mainWarehouse, $product, $i % 6 === 0 ? 5000 : random_int(20, 120));
            }
        }
    }

    private function seedOpeningStock(): void
    {
        $openingDate = now()->subDays(90);

        // Generous opening balances at the main store for everything, so the ~7 done
        // fulfillments / productions / returns below never run short of stock.
        foreach ($this->rawMaterials as $rm) {
            $this->atDate($openingDate, fn () => $this->stock->setLevel($this->mainWarehouse, $rm, random_int(2000, 6000), $this->admin, 'Opening balance'));
        }
        foreach ($this->products as $product) {
            $this->atDate($openingDate, fn () => $this->stock->setLevel($this->mainWarehouse, $product, random_int(200, 800), $this->admin, 'Opening balance'));
        }

        // A little stock at the other warehouses too (so transfers + their lists show data).
        foreach (array_slice($this->warehouses, 1) as $warehouse) {
            foreach (array_slice($this->products, 0, 5) as $product) {
                $this->atDate($openingDate, fn () => $this->stock->setLevel($warehouse, $product, random_int(40, 150), $this->admin, 'Opening balance'));
            }
        }
    }

    private function seedTransfers(): void
    {
        // ~20 transfers from the main store out to the other warehouses, spread in time.
        for ($i = 0; $i < 20; $i++) {
            $to = $this->warehouses[1 + ($i % (count($this->warehouses) - 1))];
            $item = $this->rawMaterials[array_rand($this->rawMaterials)];
            $when = $this->randomDate(80);
            $this->atDate($when, fn () => $this->stock->transfer($this->mainWarehouse, $to, $item, random_int(5, 40), $this->admin, 'Rebalance stock'));
        }
    }

    // -- Status-carrying documents (~20 each, all statuses, spread dates) --------

    private function seedPurchaseOrders(): void
    {
        // 10 pending, 7 received, 3 cancelled. Dates rise with the loop so order # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 10 ? PurchaseOrderStatus::Pending : ($i < 17 ? PurchaseOrderStatus::Pending : PurchaseOrderStatus::Cancelled);
            $when = $dates[$i];
            $supplier = $this->suppliers[array_rand($this->suppliers)];

            $po = PurchaseOrder::create(['supplier_id' => $supplier->id, 'currency' => self::CURRENCIES[array_rand(self::CURRENCIES)], 'status' => $status, 'user_id' => $this->admin->id]);
            foreach ($this->pickRawMaterials() as $rm) {
                $this->poItem($po, $rm, random_int(50, 400), random_int(1, 20) + 0.5);
            }

            if ($i >= 10 && $i < 17) {
                $this->atDate($when, fn () => app(ReceivePurchaseOrder::class)->handle($po, $this->mainWarehouse, $this->admin));
                $this->stamp($po, $when, ['received_at' => $when]);
            } else {
                $this->stamp($po, $when);
            }
        }
    }

    private function seedProductionOrders(): void
    {
        // 10 pending, 7 completed, 3 cancelled. Dates rise with the loop so order # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 10 ? ProductionOrderStatus::Pending : ($i < 17 ? ProductionOrderStatus::Pending : ProductionOrderStatus::Cancelled);
            $when = $dates[$i];
            $product = $this->manufacturable[array_rand($this->manufacturable)];
            $order = $this->productionOrder($product, random_int(5, 25));

            if ($i >= 10 && $i < 17) {
                $this->atDate($when, fn () => app(CompleteProductionOrder::class)->handle($order, $this->mainWarehouse, $this->admin));
                $this->stamp($order, $when, ['completed_at' => $when]);
            } else {
                if ($status === ProductionOrderStatus::Cancelled) {
                    $order->update(['status' => ProductionOrderStatus::Cancelled]);
                }
                $this->stamp($order, $when);
            }
        }
    }

    private function seedSalesOrders(): void
    {
        // 10 pending, 7 fulfilled, 3 cancelled. Dates rise with the loop so order # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 17 ? SalesOrderStatus::Pending : SalesOrderStatus::Cancelled;
            $when = $dates[$i];
            $customer = $this->customers[array_rand($this->customers)];

            $so = SalesOrder::create(['customer_id' => $customer->id, 'currency' => self::CURRENCIES[array_rand(self::CURRENCIES)], 'status' => $status, 'user_id' => $this->admin->id]);
            foreach ($this->pickProducts() as $product) {
                $this->soItem($so, $product, random_int(2, 15), random_int(8, 60) + 0.5);
            }

            if ($i >= 10 && $i < 17) {
                $this->atDate($when, fn () => app(FulfillSalesOrder::class)->handle($so, $this->mainWarehouse, $this->admin));
                $this->stamp($so, $when, ['fulfilled_at' => $when]);
            } else {
                $this->stamp($so, $when);
            }
        }
    }

    private function seedPurchaseReturns(): void
    {
        // 10 pending, 7 completed, 3 cancelled. Dates rise with the loop so return # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 17 ? ReturnStatus::Pending : ReturnStatus::Cancelled;
            $when = $dates[$i];
            $pr = PurchaseReturn::create(['supplier_id' => $this->suppliers[array_rand($this->suppliers)]->id, 'status' => $status, 'user_id' => $this->admin->id, 'notes' => 'Damaged on arrival']);
            $pr->items()->create($this->rmLine($this->rawMaterials[array_rand($this->rawMaterials)], random_int(2, 20)));

            if ($i >= 10 && $i < 17) {
                $this->atDate($when, fn () => app(CompletePurchaseReturn::class)->handle($pr, $this->mainWarehouse, $this->admin));
                $this->stamp($pr, $when, ['completed_at' => $when]);
            } else {
                $this->stamp($pr, $when);
            }
        }
    }

    private function seedSalesReturns(): void
    {
        // 10 pending, 7 completed, 3 cancelled. Dates rise with the loop so return # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 17 ? ReturnStatus::Pending : ReturnStatus::Cancelled;
            $when = $dates[$i];
            $sr = SalesReturn::create(['customer_id' => $this->customers[array_rand($this->customers)]->id, 'status' => $status, 'user_id' => $this->admin->id, 'notes' => 'Customer changed their mind']);
            $sr->items()->create($this->productLine($this->products[array_rand($this->products)], random_int(1, 8)));

            if ($i >= 10 && $i < 17) {
                $this->atDate($when, fn () => app(CompleteSalesReturn::class)->handle($sr, $this->mainWarehouse, $this->admin));
                $this->stamp($sr, $when, ['completed_at' => $when]);
            } else {
                $this->stamp($sr, $when);
            }
        }
    }

    private function seedStockTakes(): void
    {
        // 10 draft, 7 posted, 3 cancelled. Dates rise with the loop so the count # descends.
        $dates = $this->spreadDates(20);
        for ($i = 0; $i < 20; $i++) {
            $when = $dates[$i];

            if ($i >= 17) {
                $take = StockTake::create(['warehouse_id' => $this->mainWarehouse->id, 'status' => StockTakeStatus::Cancelled, 'user_id' => $this->admin->id]);
                $this->stamp($take, $when);

                continue;
            }

            $this->stockTake($this->mainWarehouse, post: $i >= 10, when: $when);
        }
    }

    // -- Row builders ------------------------------------------------------------

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

    /** @return list<RawMaterial> 1-2 distinct raw materials for an order. */
    private function pickRawMaterials(): array
    {
        $keys = array_rand($this->rawMaterials, min(2, count($this->rawMaterials)));

        return array_map(fn ($k) => $this->rawMaterials[$k], (array) $keys);
    }

    /** @return list<Product> 1-2 distinct products for an order. */
    private function pickProducts(): array
    {
        $keys = array_rand($this->products, min(2, count($this->products)));

        return array_map(fn ($k) => $this->products[$k], (array) $keys);
    }

    private function poItem(PurchaseOrder $po, RawMaterial $rm, float $qty, float $cost): void
    {
        $po->items()->create(['raw_material_id' => $rm->id, 'raw_material_snapshot' => $rm->snapshot(), 'quantity' => $qty, 'unit_cost' => $cost]);
    }

    private function soItem(SalesOrder $so, Product $product, float $qty, float $price): void
    {
        $so->items()->create(['product_id' => $product->id, 'product_snapshot' => $product->snapshot(), 'quantity' => $qty, 'unit_price' => $price]);
    }

    /** @return array<string, mixed> */
    private function rmLine(RawMaterial $rm, float $qty): array
    {
        return ['raw_material_id' => $rm->id, 'raw_material_snapshot' => $rm->snapshot(), 'quantity' => $qty];
    }

    /** @return array<string, mixed> */
    private function productLine(Product $product, float $qty): array
    {
        return ['product_id' => $product->id, 'product_snapshot' => $product->snapshot(), 'quantity' => $qty];
    }

    private function productionOrder(Product $product, float $qty): ProductionOrder
    {
        $product->loadMissing('bomItems.rawMaterial');

        $order = ProductionOrder::create([
            'product_id' => $product->id,
            'product_snapshot' => $product->snapshot(),
            'quantity' => $qty,
            'user_id' => $this->admin->id,
            'status' => ProductionOrderStatus::Pending,
        ]);

        foreach ($product->bomItems as $item) {
            $order->items()->create([
                'raw_material_id' => $item->raw_material_id,
                'raw_material_snapshot' => RawMaterial::snapshotOf($item->rawMaterial),
                'quantity_per_unit' => $item->quantity,
                'quantity_required' => (float) $item->quantity * $qty,
            ]);
        }

        return $order;
    }

    /**
     * Snapshot the warehouse's current on-hand into a stock take. When posting, count
     * one item short so the posted take shows a difference; backdate it to $when.
     */
    private function stockTake(Warehouse $warehouse, bool $post, CarbonInterface $when): void
    {
        $take = StockTake::create(['warehouse_id' => $warehouse->id, 'status' => StockTakeStatus::Draft, 'user_id' => $this->admin->id]);

        $stocks = WarehouseStock::where('warehouse_id', $warehouse->id)->with('stockable')->get();

        foreach ($stocks as $stock) {
            $stockable = $stock->stockable;
            if ($stockable === null) {
                continue;
            }

            $take->items()->create([
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'stockable_snapshot' => ['name' => $stockable->name, 'sku' => $stockable->sku ?? null, 'unit' => $stockable->unit ?? ''],
                'system_qty' => (float) $stock->quantity,
                'counted_qty' => (float) $stock->quantity,
                'variance' => 0,
            ]);
        }

        if (! $post) {
            $this->stamp($take, $when);

            return;
        }

        $take->load('items');
        $first = true;
        $counts = $take->items->map(function ($item) use (&$first): array {
            $counted = $first && (float) $item->counted_qty >= 1 ? (float) $item->counted_qty - 1 : (float) $item->counted_qty;
            $first = false;

            return ['id' => $item->id, 'counted_qty' => $counted];
        })->all();

        $this->atDate($when, fn () => app(PostStockTake::class)->handle($take, $counts, $this->admin));
        $this->stamp($take, $when, ['counted_at' => $when]);
    }
}
