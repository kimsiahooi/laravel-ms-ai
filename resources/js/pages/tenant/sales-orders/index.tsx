import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Ban,
    MoreHorizontal,
    PackageCheck,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { salesOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import soRoutes from '@/routes/tenant/sales-orders';
import type { TenantPageProps } from '@/types';

type SalesOrder = App.Data.SalesOrderData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    orders: Paginator<SalesOrder>;
    customers: Option[];
    products: Option[];
    locations: Option[];
};

type Line = {
    key: number;
    productId: string;
    quantity: string;
    unitPrice: string;
};

export default function SalesOrdersIndex() {
    const { orders, filters, customers, products, locations, tenant } =
        usePageProps<PageProps>();
    const base = soRoutes.index.url({ tenant: tenant.slug });

    const customerOptions = customers.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const productOptions = products.map((p) => ({
        value: String(p.id),
        label: p.name,
    }));
    const locationOptions = locations.map((l) => ({
        value: String(l.id),
        label: l.name,
    }));

    const [customerId, setCustomerId] = useState('');
    const [currency, setCurrency] = useState('USD');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<Line[]>([]);
    const lineKey = useRef(0);

    const [fulfilling, setFulfilling] = useState<SalesOrder | null>(null);
    const [fulfillLocationId, setFulfillLocationId] = useState('');
    const [fulfillProcessing, setFulfillProcessing] = useState(false);
    const [fulfillError, setFulfillError] = useState<string | null>(null);
    const [cancelling, setCancelling] = useState<SalesOrder | null>(null);

    const newLine = (): Line => ({
        key: lineKey.current++,
        productId: '',
        quantity: '',
        unitPrice: '',
    });
    const addLine = () => setLines((prev) => [...prev, newLine()]);
    const removeLine = (key: number) =>
        setLines((prev) => prev.filter((line) => line.key !== key));
    const updateLine = (key: number, patch: Partial<Line>) =>
        setLines((prev) =>
            prev.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );

    const dialog = useResourceDialog<SalesOrder>({
        onCreate: () => {
            setCustomerId('');
            setCurrency('USD');
            setNotes('');
            setLines([newLine()]);
        },
        onEdit: (order) => {
            setCustomerId('');
            setCurrency(order.currency);
            setNotes('');
            setLines(
                order.items.map((item) => ({
                    key: lineKey.current++,
                    productId: item.product_id ? String(item.product_id) : '',
                    quantity: String(item.quantity),
                    unitPrice: String(item.unit_price),
                })),
            );
        },
    });

    const draftTotal = lines.reduce(
        (sum, line) =>
            sum + (Number(line.quantity) || 0) * (Number(line.unitPrice) || 0),
        0,
    );

    const submitFulfill = () => {
        if (!fulfilling) return;
        router.post(
            soRoutes.fulfill.url({
                tenant: tenant.slug,
                salesOrder: fulfilling.id,
            }),
            { location_id: fulfillLocationId },
            {
                preserveScroll: true,
                onStart: () => {
                    setFulfillProcessing(true);
                    setFulfillError(null);
                },
                onFinish: () => setFulfillProcessing(false),
                onSuccess: () => setFulfilling(null),
                onError: (errors) =>
                    setFulfillError(
                        errors.location_id ?? 'Could not fulfill the order.',
                    ),
            },
        );
    };

    const submitCancel = () => {
        if (!cancelling) return;
        router.post(
            soRoutes.cancel.url({
                tenant: tenant.slug,
                salesOrder: cancelling.id,
            }),
            {},
            { preserveScroll: true, onSuccess: () => setCancelling(null) },
        );
    };

    const columns: ColumnDef<SalesOrder>[] = [
        {
            accessorKey: 'id',
            header: 'SO #',
            cell: ({ row }) => (
                <Link
                    href={soRoutes.show.url({
                        tenant: tenant.slug,
                        salesOrder: row.original.id,
                    })}
                    className="font-medium text-primary tabular-nums hover:underline"
                >
                    #{row.original.id}
                </Link>
            ),
        },
        {
            accessorKey: 'customer',
            header: 'Customer',
            cell: ({ row }) => row.original.customer ?? '—',
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const variant =
                    row.original.status === 'fulfilled'
                        ? 'default'
                        : row.original.status === 'cancelled'
                          ? 'outline'
                          : 'secondary';
                return (
                    <Badge variant={variant}>{row.original.status_label}</Badge>
                );
            },
        },
        {
            accessorKey: 'item_count',
            header: 'Lines',
            cell: ({ row }) => row.original.item_count,
            meta: {
                className:
                    'hidden text-right text-muted-foreground sm:table-cell',
            },
        },
        {
            accessorKey: 'total',
            header: 'Total',
            cell: ({ row }) =>
                formatMoney(row.original.total, row.original.currency),
            meta: { className: 'text-right tabular-nums' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => {
                const order = row.original;
                const pending = order.status === 'pending';

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={`Actions for SO #${order.id}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            {pending ? (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => dialog.openEdit(order)}
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            setFulfillLocationId('');
                                            setFulfillError(null);
                                            setFulfilling(order);
                                        }}
                                    >
                                        <PackageCheck className="size-4" />
                                        Fulfill
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => setCancelling(order)}
                                    >
                                        <Ban className="size-4" />
                                        Cancel
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        router.delete(`${base}/${order.id}`, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <Trash2 className="size-4" />
                                    Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: salesOrderMeta.plural, href: base },
            ]}
        >
            <Head title={salesOrderMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {salesOrderMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Sell products to customers, then fulfill them from a
                    location.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={orders}
                filters={filters}
                baseUrl={base}
                only={['orders', 'filters']}
                getRowId={(order) => String(order.id)}
                title={salesOrderMeta.plural}
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {salesOrderMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={salesOrderMeta.icon}
                        title={`No ${salesOrderMeta.plural.toLowerCase()} yet`}
                        description="Create your first sales order to start shipping stock."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {salesOrderMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={salesOrderMeta.singular}
                baseUrl={base}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                description={{
                    create: 'Sell products to a customer.',
                    edit: 'Update this pending sales order.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <input
                                    type="hidden"
                                    name="customer_id"
                                    value={customerId}
                                />
                                <ComboboxField
                                    id="customer"
                                    label="Customer"
                                    options={customerOptions}
                                    value={customerId}
                                    onChange={setCustomerId}
                                    error={errors.customer_id}
                                    placeholder="Select customer"
                                    searchPlaceholder="Search customers…"
                                    emptyText="No customers found."
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="currency">Currency</Label>
                                <Input
                                    id="currency"
                                    name="currency"
                                    value={currency}
                                    onChange={(event) =>
                                        setCurrency(
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={3}
                                    required
                                    placeholder="USD"
                                    aria-invalid={!!errors.currency}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Line items</Label>
                                <span className="text-muted-foreground text-sm tabular-nums">
                                    Total:{' '}
                                    {formatMoney(draftTotal, currency || 'USD')}
                                </span>
                            </div>

                            {errors.items ? (
                                <p className="text-destructive text-sm">
                                    {errors.items}
                                </p>
                            ) : null}

                            <div className="space-y-3">
                                {lines.map((line, index) => (
                                    <div
                                        key={line.key}
                                        className="grid grid-cols-[1fr_auto_auto_auto] items-end gap-2"
                                    >
                                        <input
                                            type="hidden"
                                            name={`items[${index}][product_id]`}
                                            value={line.productId}
                                        />
                                        <ComboboxField
                                            id={`line-${line.key}`}
                                            label="Product"
                                            options={productOptions}
                                            value={line.productId}
                                            onChange={(value) =>
                                                updateLine(line.key, {
                                                    productId: value,
                                                })
                                            }
                                            error={
                                                errors[
                                                    `items.${index}.product_id`
                                                ]
                                            }
                                            placeholder="Select"
                                            searchPlaceholder="Search…"
                                            emptyText="None found."
                                        />
                                        <div className="w-24 space-y-2">
                                            <Label
                                                htmlFor={`qty-${line.key}`}
                                                className="text-muted-foreground text-xs"
                                            >
                                                Qty
                                            </Label>
                                            <Input
                                                id={`qty-${line.key}`}
                                                name={`items[${index}][quantity]`}
                                                type="number"
                                                min={0}
                                                step="any"
                                                value={line.quantity}
                                                onChange={(event) =>
                                                    updateLine(line.key, {
                                                        quantity:
                                                            event.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="w-28 space-y-2">
                                            <Label
                                                htmlFor={`price-${line.key}`}
                                                className="text-muted-foreground text-xs"
                                            >
                                                Unit price
                                            </Label>
                                            <Input
                                                id={`price-${line.key}`}
                                                name={`items[${index}][unit_price]`}
                                                type="number"
                                                min={0}
                                                step="any"
                                                value={line.unitPrice}
                                                onChange={(event) =>
                                                    updateLine(line.key, {
                                                        unitPrice:
                                                            event.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 text-muted-foreground"
                                            onClick={() => removeLine(line.key)}
                                            disabled={lines.length === 1}
                                            aria-label="Remove line"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addLine}
                            >
                                <Plus className="size-4" />
                                Add line
                            </Button>
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
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>

            {/* Fulfill dialog */}
            <Dialog
                open={fulfilling !== null}
                onOpenChange={(next) => {
                    if (!next) setFulfilling(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Fulfill SO #{fulfilling?.id}</DialogTitle>
                        <DialogDescription>
                            Ship each line from a location as a stock
                            fulfillment.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="fulfill-location"
                        label="Fulfill from"
                        options={locationOptions}
                        value={fulfillLocationId}
                        onChange={setFulfillLocationId}
                        error={fulfillError ?? undefined}
                        placeholder="Select location"
                        searchPlaceholder="Search locations…"
                        emptyText="No locations found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={fulfillProcessing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitFulfill}
                            disabled={fulfillProcessing || !fulfillLocationId}
                        >
                            Fulfill
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Cancel confirmation */}
            <Dialog
                open={cancelling !== null}
                onOpenChange={(next) => {
                    if (!next) setCancelling(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel sales order</DialogTitle>
                        <DialogDescription>
                            Cancel SO #{cancelling?.id}? It can no longer be
                            fulfilled or edited.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="ghost">Keep order</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={submitCancel}>
                            <Ban className="size-4" />
                            Cancel order
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
