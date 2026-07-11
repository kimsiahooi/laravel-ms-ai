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
import { FieldLabel } from '@/components/field-label';
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
import { purchaseOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import poRoutes from '@/routes/tenant/purchase-orders';
import type { TenantPageProps } from '@/types';

type PurchaseOrder = App.Data.PurchaseOrderData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    orders: Paginator<PurchaseOrder>;
    suppliers: Option[];
    rawMaterials: Option[];
    warehouses: Option[];
};

type Line = {
    key: number;
    rawMaterialId: string;
    quantity: string;
    unitCost: string;
};

export default function PurchaseOrdersIndex() {
    const { orders, filters, suppliers, rawMaterials, warehouses, tenant } =
        usePageProps<PageProps>();
    const base = poRoutes.index.url({ tenant: tenant.slug });

    const supplierOptions = suppliers.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));
    const rawMaterialOptions = rawMaterials.map((r) => ({
        value: String(r.id),
        label: r.name,
    }));
    const warehouseOptions = warehouses.map((w) => ({
        value: String(w.id),
        label: w.name,
    }));

    const [supplierId, setSupplierId] = useState('');
    const [currency, setCurrency] = useState('USD');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<Line[]>([]);
    const lineKey = useRef(0);

    const [receiving, setReceiving] = useState<PurchaseOrder | null>(null);
    const [receiveWarehouseId, setReceiveWarehouseId] = useState('');
    const [receiveProcessing, setReceiveProcessing] = useState(false);
    const [cancelling, setCancelling] = useState<PurchaseOrder | null>(null);

    const newLine = (): Line => ({
        key: lineKey.current++,
        rawMaterialId: '',
        quantity: '',
        unitCost: '',
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

    const dialog = useResourceDialog<PurchaseOrder>({
        onCreate: () => {
            setSupplierId('');
            setCurrency('USD');
            setNotes('');
            setLines([newLine()]);
        },
        onEdit: (order) => {
            setSupplierId('');
            setCurrency(order.currency);
            setNotes('');
            setLines(
                order.items.map((item) => ({
                    key: lineKey.current++,
                    rawMaterialId: item.raw_material_id
                        ? String(item.raw_material_id)
                        : '',
                    quantity: String(item.quantity),
                    unitCost: String(item.unit_cost),
                })),
            );
        },
    });

    const draftTotal = lines.reduce(
        (sum, line) =>
            sum + (Number(line.quantity) || 0) * (Number(line.unitCost) || 0),
        0,
    );

    const submitReceive = () => {
        if (!receiving) return;
        router.post(
            poRoutes.receive.url({
                tenant: tenant.slug,
                purchaseOrder: receiving.id,
            }),
            { warehouse_id: receiveWarehouseId },
            {
                preserveScroll: true,
                onStart: () => setReceiveProcessing(true),
                onFinish: () => setReceiveProcessing(false),
                onSuccess: () => setReceiving(null),
            },
        );
    };

    const submitCancel = () => {
        if (!cancelling) return;
        router.post(
            poRoutes.cancel.url({
                tenant: tenant.slug,
                purchaseOrder: cancelling.id,
            }),
            {},
            { preserveScroll: true, onSuccess: () => setCancelling(null) },
        );
    };

    const columns: ColumnDef<PurchaseOrder>[] = [
        {
            accessorKey: 'id',
            header: 'PO #',
            cell: ({ row }) => (
                <Link
                    href={poRoutes.show.url({
                        tenant: tenant.slug,
                        purchaseOrder: row.original.id,
                    })}
                    className="font-medium text-primary tabular-nums hover:underline"
                >
                    #{row.original.id}
                </Link>
            ),
        },
        {
            accessorKey: 'supplier',
            header: 'Supplier',
            cell: ({ row }) => row.original.supplier ?? '—',
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const variant =
                    row.original.status === 'received'
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
                                aria-label={`Actions for PO #${order.id}`}
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
                                            setReceiveWarehouseId('');
                                            setReceiving(order);
                                        }}
                                    >
                                        <PackageCheck className="size-4" />
                                        Receive
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
                { title: purchaseOrderMeta.plural, href: base },
            ]}
        >
            <Head title={purchaseOrderMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {purchaseOrderMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Order raw materials from suppliers, then receive them into a
                    warehouse.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={orders}
                filters={filters}
                baseUrl={base}
                only={['orders', 'filters']}
                getRowId={(order) => String(order.id)}
                title={purchaseOrderMeta.plural}
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {purchaseOrderMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={purchaseOrderMeta.icon}
                        title={`No ${purchaseOrderMeta.plural.toLowerCase()} yet`}
                        description="Create your first purchase order to start receiving stock."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {purchaseOrderMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={purchaseOrderMeta.singular}
                baseUrl={base}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                description={{
                    create: 'Order raw materials from a supplier.',
                    edit: 'Update this pending purchase order.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <input
                                    type="hidden"
                                    name="supplier_id"
                                    value={supplierId}
                                />
                                <ComboboxField
                                    id="supplier"
                                    label="Supplier"
                                    options={supplierOptions}
                                    value={supplierId}
                                    onChange={setSupplierId}
                                    error={errors.supplier_id}
                                    placeholder="Select supplier"
                                    searchPlaceholder="Search suppliers…"
                                    emptyText="No suppliers found."
                                />
                            </div>
                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="currency"
                                    hint="The 3-letter currency code for this order's prices, such as USD, MYR, or EUR."
                                >
                                    Currency
                                </FieldLabel>
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
                                            name={`items[${index}][raw_material_id]`}
                                            value={line.rawMaterialId}
                                        />
                                        <ComboboxField
                                            id={`line-${line.key}`}
                                            label="Raw material"
                                            options={rawMaterialOptions}
                                            value={line.rawMaterialId}
                                            onChange={(value) =>
                                                updateLine(line.key, {
                                                    rawMaterialId: value,
                                                })
                                            }
                                            error={
                                                errors[
                                                    `items.${index}.raw_material_id`
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
                                                htmlFor={`cost-${line.key}`}
                                                className="text-muted-foreground text-xs"
                                            >
                                                Unit cost
                                            </Label>
                                            <Input
                                                id={`cost-${line.key}`}
                                                name={`items[${index}][unit_cost]`}
                                                type="number"
                                                min={0}
                                                step="any"
                                                value={line.unitCost}
                                                onChange={(event) =>
                                                    updateLine(line.key, {
                                                        unitCost:
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

            {/* Receive dialog */}
            <Dialog
                open={receiving !== null}
                onOpenChange={(next) => {
                    if (!next) setReceiving(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Receive PO #{receiving?.id}</DialogTitle>
                        <DialogDescription>
                            Post each line into a warehouse as a stock receipt.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="receive-warehouse"
                        label="Receive into"
                        hint="The warehouse received stock is added to."
                        options={warehouseOptions}
                        value={receiveWarehouseId}
                        onChange={setReceiveWarehouseId}
                        placeholder="Select warehouse"
                        searchPlaceholder="Search warehouses…"
                        emptyText="No warehouses found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={receiveProcessing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitReceive}
                            disabled={receiveProcessing || !receiveWarehouseId}
                        >
                            Receive
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
                        <DialogTitle>Cancel purchase order</DialogTitle>
                        <DialogDescription>
                            Cancel PO #{cancelling?.id}? It can no longer be
                            received or edited.
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
