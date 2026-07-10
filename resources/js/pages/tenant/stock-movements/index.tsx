import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { stockMovementMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { timeAgo } from '@/lib/format';
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
    locations: Option[];
    items: ItemOption[];
};

const TYPES: { value: MovementType; label: string }[] = [
    { value: 'in', label: 'In' },
    { value: 'out', label: 'Out' },
    { value: 'adjustment', label: 'Adjust' },
];

export default function StockMovementsIndex() {
    const { movements, filters, locations, items, tenant } =
        usePageProps<PageProps>();
    const base = stockMovementsRoutes.index.url({ tenant: tenant.slug });

    const locationOptions = locations.map((location) => ({
        value: String(location.id),
        label: location.name,
    }));

    const [locationId, setLocationId] = useState('');
    const [stockable, setStockable] = useState('');
    const [type, setType] = useState<MovementType>('in');
    const [quantity, setQuantity] = useState('');
    const [notes, setNotes] = useState('');

    const dialog = useResourceDialog<StockMovement>({
        onCreate: () => {
            setLocationId('');
            setStockable('');
            setType('in');
            setQuantity('');
            setNotes('');
        },
    });

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
            accessorKey: 'location',
            header: 'Location',
            cell: ({ row }) => row.original.location,
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
            header: 'Qty',
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
                        {q.toLocaleString(undefined, {
                            maximumFractionDigits: 4,
                        })}
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
            header: 'By',
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
                    The append-only ledger of every on-hand change.
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
                        description="Record your first movement to start tracking on-hand levels."
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
                    create: 'Adjust on-hand at a location.',
                    edit: '',
                }}
            >
                {({ errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="location_id"
                            value={locationId}
                        />
                        <input
                            type="hidden"
                            name="stockable"
                            value={stockable}
                        />
                        <input type="hidden" name="type" value={type} />

                        <ComboboxField
                            id="location"
                            label="Location"
                            options={locationOptions}
                            value={locationId}
                            onChange={setLocationId}
                            error={errors.location_id}
                            placeholder="Select location"
                            searchPlaceholder="Search locations…"
                            emptyText="No locations found."
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
                            <Label>Type</Label>
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
                                    ? 'Set on-hand to'
                                    : 'Quantity'}
                            </Label>
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
                                placeholder="Reason for this adjustment…"
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>
        </TenantLayout>
    );
}
