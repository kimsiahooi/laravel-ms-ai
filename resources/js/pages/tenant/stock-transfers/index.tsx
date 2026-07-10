import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { stockTransferMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import stockTransfersRoutes from '@/routes/tenant/stock-transfers';
import type { TenantPageProps } from '@/types';

type StockTransfer = App.Data.StockTransferData;
type Option = App.Data.OptionData;
type ItemOption = { value: string; label: string };

type PageProps = TenantPageProps & {
    transfers: Paginator<StockTransfer>;
    locations: Option[];
    items: ItemOption[];
};

export default function StockTransfersIndex() {
    const { transfers, filters, locations, items, tenant } =
        usePageProps<PageProps>();
    const base = stockTransfersRoutes.index.url({ tenant: tenant.slug });

    const locationOptions = locations.map((location) => ({
        value: String(location.id),
        label: location.name,
    }));

    const [stockable, setStockable] = useState('');
    const [fromLocationId, setFromLocationId] = useState('');
    const [toLocationId, setToLocationId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [notes, setNotes] = useState('');

    const dialog = useResourceDialog<StockTransfer>({
        onCreate: () => {
            setStockable('');
            setFromLocationId('');
            setToLocationId('');
            setQuantity('');
            setNotes('');
        },
    });

    const columns: ColumnDef<StockTransfer>[] = [
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
            accessorKey: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.item}
                </span>
            ),
        },
        {
            id: 'route',
            header: 'From → To',
            cell: ({ row }) => (
                <span className="flex items-center gap-1.5 whitespace-nowrap text-muted-foreground text-sm">
                    {row.original.from}
                    <ArrowRight className="size-3.5 shrink-0" />
                    {row.original.to}
                </span>
            ),
        },
        {
            accessorKey: 'quantity',
            header: 'Qty',
            cell: ({ row }) => formatQuantity(row.original.quantity),
            meta: { className: 'text-right tabular-nums' },
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
                { title: stockTransferMeta.plural, href: base },
            ]}
        >
            <Head title={stockTransferMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {stockTransferMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Move stock from one location to another.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={transfers}
                filters={filters}
                baseUrl={base}
                only={['transfers', 'filters']}
                getRowId={(transfer) => String(transfer.id)}
                title={stockTransferMeta.plural}
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {stockTransferMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={stockTransferMeta.icon}
                        title={`No ${stockTransferMeta.plural.toLowerCase()} yet`}
                        description="Move stock between locations to see transfers here."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {stockTransferMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={stockTransferMeta.singular}
                baseUrl={base}
                description={{
                    create: 'Move stock from a source to a destination location.',
                    edit: '',
                }}
            >
                {({ errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="stockable"
                            value={stockable}
                        />
                        <input
                            type="hidden"
                            name="from_location_id"
                            value={fromLocationId}
                        />
                        <input
                            type="hidden"
                            name="to_location_id"
                            value={toLocationId}
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

                        <ComboboxField
                            id="from_location"
                            label="From"
                            options={locationOptions}
                            value={fromLocationId}
                            onChange={setFromLocationId}
                            error={errors.from_location_id}
                            placeholder="Source location"
                            searchPlaceholder="Search locations…"
                            emptyText="No locations found."
                        />

                        <ComboboxField
                            id="to_location"
                            label="To"
                            options={locationOptions}
                            value={toLocationId}
                            onChange={setToLocationId}
                            error={errors.to_location_id}
                            placeholder="Destination location"
                            searchPlaceholder="Search locations…"
                            emptyText="No locations found."
                        />

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="quantity"
                                hint="How much stock to move. It can't exceed what's on hand at the “From” location."
                            >
                                Quantity
                            </FieldLabel>
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
                                placeholder="Reason for this transfer…"
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>
        </TenantLayout>
    );
}
