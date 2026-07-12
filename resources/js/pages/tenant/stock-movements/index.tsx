import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { OnHandHint } from '@/components/on-hand-hint';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { stockMovementMeta } from '@/config/resources';
import { useOnHand } from '@/hooks/use-on-hand';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import stockMovementsRoutes from '@/routes/tenant/stock-movements';
import type { TenantPageProps } from '@/types';

type StockMovement = App.Data.StockMovementData;
type Option = App.Data.OptionData;
type ItemOption = { value: string; label: string };
type MovementType = 'in' | 'out' | 'adjustment';

type PageProps = TenantPageProps & {
    movements: Paginator<StockMovement>;
    warehouses: Option[];
    items: ItemOption[];
};

const TYPES: { value: MovementType; label: string }[] = [
    { value: 'in', label: 'In' },
    { value: 'out', label: 'Out' },
    { value: 'adjustment', label: 'Adjustment' },
];

export default function StockMovementsIndex() {
    const { movements, filters, warehouses, items, tenant } =
        usePageProps<PageProps>();
    const base = stockMovementsRoutes.index.url({ tenant: tenant.slug });

    const warehouseOptions = warehouses.map((warehouse) => ({
        value: String(warehouse.id),
        label: warehouse.name,
    }));

    const [warehouseId, setWarehouseId] = useState('');
    const [stockable, setStockable] = useState('');
    const [type, setType] = useState<MovementType>('in');
    const [quantity, setQuantity] = useState('');
    const [notes, setNotes] = useState('');

    const dialog = useResourceDialog<StockMovement>({
        onCreate: () => {
            setWarehouseId('');
            setStockable('');
            setType('in');
            setQuantity('');
            setNotes('');
        },
    });

    const onHand = useOnHand(tenant.slug, warehouseId, stockable);

    // Deep-link: /stock-movements?warehouse={id} opens the create dialog pre-scoped
    // to that warehouse. openCreate() runs the onCreate reset (clears warehouseId),
    // so it must fire BEFORE setWarehouseId — the pre-fill is the last write and
    // wins. One-shot: strip the param so a reload / Back-Forward doesn't reopen a
    // dismissed dialog.
    // biome-ignore lint/correctness/useExhaustiveDependencies: intentional one-time mount effect
    useEffect(() => {
        const id = new URLSearchParams(window.location.search).get('warehouse');
        if (id && warehouseOptions.some((o) => o.value === id)) {
            dialog.openCreate();
            setWarehouseId(id);
            const url = new URL(window.location.href);
            url.searchParams.delete('warehouse');
            window.history.replaceState(window.history.state, '', url);
        }
    }, []);

    const columns: ColumnDef<StockMovement>[] = [
        {
            accessorKey: 'created_at',
            header: 'When',
            cell: ({ row }) => (
                <span
                    className="whitespace-nowrap text-muted-foreground tabular-nums"
                    suppressHydrationWarning
                >
                    {timeAgo(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'warehouse',
            header: 'Warehouse',
            cell: ({ row }) => row.original.warehouse,
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.item}
                </span>
            ),
        },
        {
            accessorKey: 'quantity',
            header: 'Quantity',
            cell: ({ row }) => {
                const q = row.original.quantity;
                return (
                    <span
                        className={cn(
                            'font-medium tabular-nums',
                            q < 0
                                ? 'text-destructive'
                                : 'text-emerald-600 dark:text-emerald-400',
                        )}
                    >
                        {q > 0 ? '+' : ''}
                        {formatQuantity(q)}
                    </span>
                );
            },
            meta: { className: 'text-right' },
        },
        {
            accessorKey: 'reason',
            header: 'Reason',
            cell: ({ row }) => row.original.reason,
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
        },
        {
            accessorKey: 'user',
            header: 'Performed by',
            cell: ({ row }) => row.original.user ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: stockMovementMeta.plural, href: base },
            ]}
        >
            <Head title={stockMovementMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {stockMovementMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    A running history of every time stock went up or down, and
                    why.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={movements}
                filters={filters}
                baseUrl={base}
                only={['movements', 'filters']}
                getRowId={(movement) => String(movement.id)}
                title={stockMovementMeta.plural}
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {stockMovementMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={stockMovementMeta.icon}
                        title={`No ${stockMovementMeta.plural.toLowerCase()} yet`}
                        description="Record your first movement to start tracking your stock levels."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {stockMovementMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={stockMovementMeta.singular}
                baseUrl={base}
                description={{
                    create: 'Adjust the stock held at a warehouse.',
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
                        <input
                            type="hidden"
                            name="stockable"
                            value={stockable}
                        />
                        <input type="hidden" name="type" value={type} />

                        <ComboboxField
                            id="warehouse"
                            label="Warehouse"
                            hint="The warehouse whose stock this movement changes."
                            options={warehouseOptions}
                            value={warehouseId}
                            onChange={setWarehouseId}
                            error={errors.warehouse_id}
                            placeholder="Select warehouse"
                            searchPlaceholder="Search warehouses…"
                            emptyText="No warehouses found."
                        />

                        <ComboboxField
                            id="stockable"
                            label="Item"
                            options={items}
                            value={stockable}
                            onChange={setStockable}
                            error={errors.stockable}
                            placeholder="Select item"
                            searchPlaceholder="Search products or raw materials…"
                            emptyText="No items found."
                        />

                        <div className="space-y-2">
                            <FieldLabel hint="In adds stock, Out removes it, and Adjustment sets the amount in stock to an exact figure — handy after a physical count.">
                                Movement type
                            </FieldLabel>
                            <div className="grid grid-cols-3 gap-2">
                                {TYPES.map((option) => (
                                    <Button
                                        key={option.value}
                                        type="button"
                                        variant={
                                            type === option.value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setType(option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="quantity">
                                {type === 'adjustment'
                                    ? 'Set stock to'
                                    : 'Quantity'}
                            </Label>
                            <OnHandHint
                                data={onHand.data}
                                loading={onHand.loading}
                            />
                            <Input
                                id="quantity"
                                name="quantity"
                                type="number"
                                min={0}
                                step="any"
                                value={quantity}
                                onChange={(event) =>
                                    setQuantity(event.target.value)
                                }
                                required
                                autoFocus
                                placeholder="0"
                                aria-invalid={!!errors.quantity}
                                aria-describedby={
                                    errors.quantity
                                        ? 'quantity-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="quantity-error"
                                role="alert"
                                message={errors.quantity}
                            />
                        </div>

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
                                placeholder="Reason for this movement…"
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>
        </TenantLayout>
    );
}
