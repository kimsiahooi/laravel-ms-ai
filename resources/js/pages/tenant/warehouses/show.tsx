import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    LoaderCircle,
    MapPin,
    Package,
    Plus,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { InfoHint } from '@/components/info-hint';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { WarningBadge } from '@/components/warning-badge';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import productsRoutes from '@/routes/tenant/products';
import stockMovementsRoutes from '@/routes/tenant/stock-movements';
import stockTransfersRoutes from '@/routes/tenant/stock-transfers';
import warehousesRoutes from '@/routes/tenant/warehouses';
import type { TenantPageProps } from '@/types';

type Warehouse = App.Data.WarehouseData;
type Item = App.Data.WarehouseItemData;

type PageProps = TenantPageProps & {
    warehouse: Warehouse;
    items: Paginator<Item>;
    summary: { in_stock: number; needs_reorder: number };
    filters: { search: string; per_page: number; view: string };
};

// Inline, per-row reorder-level editor. Controlled over local state (seeded from
// the row) so an uncommitted keystroke shows and a dropped refresh doesn't blank
// it. Commits on Enter/blur via a per-cell Inertia useForm; the spinner is driven
// by the form's `processing` flag, and an async visit keeps row saves independent.
function MinStockCell({
    row,
    warehouseId,
    tenantSlug,
}: {
    row: Item;
    warehouseId: number;
    tenantSlug: string;
}) {
    const [value, setValue] = useState(String(row.min_stock));
    const form = useForm({
        stockable_type: row.stockable_type,
        stockable_id: row.stockable_id,
        min_stock: row.min_stock,
    });

    const commit = () => {
        if (value === String(row.min_stock)) return;
        form.transform((data) => ({
            ...data,
            min_stock: value === '' ? 0 : Number(value),
        }));
        form.put(
            warehousesRoutes.reorderLevels.update.url({
                tenant: tenantSlug,
                warehouse: warehouseId,
            }),
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                only: ['items', 'summary'],
                onError: () => {
                    setValue(String(row.min_stock));
                    toast.error('Could not update the reorder level.');
                },
            },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1.5">
            {form.processing && (
                <LoaderCircle className="size-3.5 animate-spin text-muted-foreground" />
            )}
            <Input
                type="number"
                min={0}
                inputMode="decimal"
                value={value}
                disabled={form.processing}
                aria-label={`Reorder level for ${row.item}`}
                onChange={(e) => setValue(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') e.currentTarget.blur();
                }}
                className="h-8 w-24 text-right tabular-nums"
            />
        </div>
    );
}

export default function WarehouseShow() {
    const { warehouse, items, summary, filters, tenant } =
        usePageProps<PageProps>();

    const listBase = warehousesRoutes.index.url({ tenant: tenant.slug });
    // View-aware base so the DataTable's own search/per-page reloads keep ?view=all.
    const base = warehousesRoutes.show.url(
        { tenant: tenant.slug, warehouse: warehouse.id },
        filters.view === 'all' ? { query: { view: 'all' } } : undefined,
    );

    const setView = (next: string) => {
        router.get(
            warehousesRoutes.show.url(
                { tenant: tenant.slug, warehouse: warehouse.id },
                {
                    query: {
                        view: next === 'all' ? 'all' : undefined,
                        search: filters.search || undefined,
                        per_page: filters.per_page,
                    },
                },
            ),
            {},
            {
                only: ['items', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const subline = [warehouse.location, warehouse.code, warehouse.address]
        .filter(Boolean)
        .join(' · ');

    const columns: ColumnDef<Item>[] = [
        {
            accessorKey: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium text-foreground">
                        {row.original.item}
                    </span>
                    {row.original.needs_reorder && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <WarningBadge tabIndex={0} className="gap-1">
                                    <TriangleAlert className="size-3" />
                                    Reorder
                                </WarningBadge>
                            </TooltipTrigger>
                            <TooltipContent>
                                On hand ({formatQuantity(row.original.on_hand)})
                                is below this warehouse's reorder level (
                                {formatQuantity(row.original.min_stock)}).
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'sku',
            header: () => (
                <>
                    SKU
                    <InfoHint>
                        The unique code assigned to this item — it appears on
                        labels, orders, and stock lists.
                    </InfoHint>
                </>
            ),
            cell: ({ row }) =>
                row.original.sku ? (
                    <span className="font-mono text-muted-foreground text-xs">
                        {row.original.sku}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => row.original.type,
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'on_hand',
            header: () => (
                <>
                    On hand
                    <InfoHint>
                        The amount you have in this warehouse right now.
                    </InfoHint>
                </>
            ),
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {formatQuantity(row.original.on_hand)}
                </span>
            ),
        },
        {
            id: 'min_here',
            header: () => (
                <>
                    Reorder level
                    <InfoHint>
                        When on hand drops to this level, it's time to restock.
                        Type to change it.
                    </InfoHint>
                </>
            ),
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <MinStockCell
                    row={row.original}
                    warehouseId={warehouse.id}
                    tenantSlug={tenant.slug}
                />
            ),
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'text-muted-foreground' },
        },
    ];

    // Two empty-state variants: the "All items" view points a brand-new tenant at
    // their (empty) catalog; the default in-stock view points at stock adjustment.
    const emptyState =
        filters.view === 'all' ? (
            <EmptyState
                icon={Package}
                title="No items in your catalog yet"
                description="Add a product or raw material first — then set its reorder level for this warehouse."
                action={
                    <Button asChild>
                        <Link
                            href={productsRoutes.index.url({
                                tenant: tenant.slug,
                            })}
                        >
                            <Plus className="size-4" />
                            Go to Products
                        </Link>
                    </Button>
                }
            />
        ) : (
            <EmptyState
                icon={Package}
                title="No stock in this warehouse yet"
                description="Receive or adjust stock to see it here — or switch to All items to set reorder levels."
                action={
                    <Button asChild>
                        <Link
                            href={stockMovementsRoutes.index.url(
                                { tenant: tenant.slug },
                                { query: { warehouse: warehouse.id } },
                            )}
                        >
                            <Plus className="size-4" />
                            Adjust stock
                        </Link>
                    </Button>
                }
            />
        );

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Warehouses', href: listBase },
                { title: warehouse.name, href: base },
            ]}
        >
            <Head title={`${warehouse.name} — Stock`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {warehouse.name}
                    </h1>
                    {subline && (
                        <p className="text-muted-foreground text-sm">
                            {subline}
                        </p>
                    )}
                </div>
                <div className="flex shrink-0 gap-2">
                    <Button asChild>
                        <Link
                            href={stockMovementsRoutes.index.url(
                                { tenant: tenant.slug },
                                { query: { warehouse: warehouse.id } },
                            )}
                        >
                            <Plus className="size-4" />
                            Adjust stock
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={stockTransfersRoutes.index.url(
                                { tenant: tenant.slug },
                                { query: { from: warehouse.id } },
                            )}
                        >
                            Transfer stock
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="flex items-center gap-3 p-5">
                        <Package className="size-5 text-muted-foreground" />
                        <div>
                            <p className="font-semibold text-2xl tabular-nums">
                                {summary.in_stock}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                In stock
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={cn(
                        summary.needs_reorder > 0 &&
                            'border-amber-500/40 bg-amber-500/5 dark:border-amber-400/40 dark:bg-amber-400/10',
                    )}
                >
                    <CardContent className="flex items-center gap-3 p-5">
                        <TriangleAlert
                            className={cn(
                                'size-5 text-muted-foreground',
                                summary.needs_reorder > 0 &&
                                    'text-amber-600 dark:text-amber-400',
                            )}
                        />
                        <div>
                            <p className="font-semibold text-2xl tabular-nums">
                                {summary.needs_reorder}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Needs reorder
                            </p>
                            <p className="text-muted-foreground text-xs">
                                below this warehouse's reorder level
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex items-center gap-3 p-5">
                        <MapPin className="size-5 text-muted-foreground" />
                        <div>
                            <p className="truncate font-semibold text-lg">
                                {warehouse.location ?? '—'}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Location
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="flex items-center justify-between gap-3">
                <Tabs
                    value={filters.view === 'all' ? 'all' : 'in_stock'}
                    onValueChange={setView}
                >
                    <TabsList>
                        <TabsTrigger value="in_stock">In stock</TabsTrigger>
                        <TabsTrigger value="all">All items</TabsTrigger>
                    </TabsList>
                </Tabs>
                {filters.view === 'all' && (
                    <p className="text-muted-foreground text-xs">
                        Set a reorder level even for items not yet stocked here.
                    </p>
                )}
            </div>

            <DataTable
                columns={columns}
                paginator={items}
                filters={filters}
                baseUrl={base}
                only={['items', 'filters']}
                getRowId={(item) =>
                    `${item.stockable_type}:${item.stockable_id}`
                }
                title={`${warehouse.name} — Stock`}
                searchPlaceholder="Search item or SKU…"
                emptyState={emptyState}
            />
        </TenantLayout>
    );
}
