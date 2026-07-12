import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ClipboardCheck,
    Eye,
    MoreHorizontal,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { SignedQuantity } from '@/components/signed-quantity';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { timeAgo } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { dashboard } from '@/routes/tenant';
import stockTakesRoutes from '@/routes/tenant/stock-takes';
import type { TenantPageProps } from '@/types';

type StockTake = App.Data.StockTakeData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    takes: Paginator<StockTake>;
    warehouses: Option[];
};

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'posted') return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}

export default function StockTakesIndex() {
    const { takes, filters, warehouses, tenant } = usePageProps<PageProps>();
    const base = stockTakesRoutes.index.url({ tenant: tenant.slug });

    const warehouseOptions = toOptions(warehouses);

    const [warehouseId, setWarehouseId] = useState('');
    const [notes, setNotes] = useState('');

    const dialog = useResourceDialog<StockTake>({
        onCreate: () => {
            setWarehouseId('');
            setNotes('');
        },
    });
    const del = useDelete<StockTake>({ baseUrl: base });

    const columns: ColumnDef<StockTake>[] = [
        {
            accessorKey: 'id',
            header: 'Count #',
            cell: ({ row }) => (
                <Link
                    href={stockTakesRoutes.show.url({
                        tenant: tenant.slug,
                        stockTake: row.original.id,
                    })}
                    className="font-medium text-primary tabular-nums hover:underline"
                >
                    #{row.original.id}
                </Link>
            ),
        },
        {
            accessorKey: 'warehouse',
            header: 'Warehouse',
            cell: ({ row }) => row.original.warehouse ?? '—',
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <Badge variant={statusVariant(row.original.status)}>
                    {row.original.status_label}
                </Badge>
            ),
        },
        {
            accessorKey: 'item_count',
            header: 'Items',
            cell: ({ row }) => row.original.item_count,
            meta: {
                className:
                    'hidden text-right text-muted-foreground sm:table-cell',
            },
        },
        {
            accessorKey: 'total_variance',
            header: 'Difference',
            cell: ({ row }) => (
                <SignedQuantity value={row.original.total_variance} />
            ),
            meta: { className: 'text-right' },
        },
        {
            accessorKey: 'created_at',
            header: 'Started',
            cell: ({ row }) => (
                <span
                    className="whitespace-nowrap text-muted-foreground tabular-nums"
                    suppressHydrationWarning
                >
                    {timeAgo(row.original.created_at)}
                </span>
            ),
            meta: { className: 'hidden lg:table-cell' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Actions for stock take #${row.original.id}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link
                                href={stockTakesRoutes.show.url({
                                    tenant: tenant.slug,
                                    stockTake: row.original.id,
                                })}
                            >
                                <Eye className="size-4" />
                                View
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => del.request(row.original)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Stock takes', href: base },
            ]}
        >
            <Head title="Stock takes" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Stock takes
                </h1>
                <p className="text-muted-foreground text-sm">
                    Count what's physically in a warehouse, then apply the
                    differences to correct your stock.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={takes}
                filters={filters}
                baseUrl={base}
                only={['takes', 'filters']}
                getRowId={(take) => String(take.id)}
                title="Stock takes"
                searchPlaceholder="Search by warehouse…"
                rowHref={(take) =>
                    stockTakesRoutes.show.url({
                        tenant: tenant.slug,
                        stockTake: take.id,
                    })
                }
                toolbar={
                    <Button
                        onClick={dialog.openCreate}
                        className="shrink-0"
                        disabled={warehouses.length === 0}
                    >
                        <Plus className="size-4" />
                        New stock take
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={ClipboardCheck}
                        title="No stock takes yet"
                        description={
                            warehouses.length === 0
                                ? 'Add a warehouse first, then you can count its stock here.'
                                : 'Start a stock take to count a warehouse and correct any differences.'
                        }
                        action={
                            warehouses.length > 0 ? (
                                <Button onClick={dialog.openCreate}>
                                    <Plus className="size-4" />
                                    New stock take
                                </Button>
                            ) : undefined
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel="stock take"
                baseUrl={base}
                description={{
                    create: "Pick a warehouse. We'll list everything it currently holds for you to count.",
                    edit: '',
                }}
            >
                {({ errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="warehouse_id"
                            value={warehouseId}
                        />
                        <ComboboxField
                            id="warehouse"
                            label="Warehouse"
                            hint="The warehouse you're counting."
                            options={warehouseOptions}
                            value={warehouseId}
                            onChange={setWarehouseId}
                            error={errors.warehouse_id}
                            placeholder="Select warehouse"
                            searchPlaceholder="Search warehouses…"
                            emptyText="No warehouses found."
                        />
                        <div className="space-y-2">
                            <Label htmlFor="notes">
                                Notes{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Textarea
                                id="notes"
                                name="notes"
                                value={notes}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                placeholder="e.g. Month-end count"
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>

            <ConfirmDeleteDialog
                item={del.deleting}
                onOpenChange={(next) => {
                    if (!next) del.cancel();
                }}
                onConfirm={del.confirm}
                title="Delete stock take"
                description={
                    <>
                        Remove stock take #{del.deleting?.id}? Its record is
                        removed; any stock corrections already applied stay.
                    </>
                }
            />
        </TenantLayout>
    );
}
