import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Ban,
    Factory,
    LoaderCircle,
    MoreHorizontal,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
import { productionOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import productionRoutes from '@/routes/tenant/production-orders';
import type { TenantPageProps } from '@/types';

type ProductionOrder = App.Data.ProductionOrderData;
type Option = App.Data.OptionData;

/** One raw-material need per built unit, for the create-dialog consumption preview. */
type BomPreviewLine = { name: string; quantity: number };

type PageProps = TenantPageProps & {
    orders: Paginator<ProductionOrder>;
    products: Option[];
    productBoms: Record<string, BomPreviewLine[]>;
    warehouses: Option[];
};

export default function ProductionOrdersIndex() {
    const { orders, filters, products, productBoms, warehouses, tenant } =
        usePageProps<PageProps>();
    const base = productionRoutes.index.url({ tenant: tenant.slug });

    const productOptions = products.map((p) => ({
        value: String(p.id),
        label: p.name,
    }));
    const warehouseOptions = warehouses.map((w) => ({
        value: String(w.id),
        label: w.name,
    }));

    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [notes, setNotes] = useState('');

    const [completing, setCompleting] = useState<ProductionOrder | null>(null);
    const [cancelling, setCancelling] = useState<ProductionOrder | null>(null);
    const completeForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const dialog = useResourceDialog<ProductionOrder>({
        onCreate: () => {
            setProductId('');
            setQuantity('1');
            setNotes('');
        },
    });

    // Live "what this build consumes" preview: each material's per-unit need × qty.
    const previewBom = productId ? (productBoms[productId] ?? []) : [];
    const buildQty = Number(quantity) || 0;

    const submitComplete = () => {
        if (!completing) return;
        completeForm.post(
            productionRoutes.complete.url({
                tenant: tenant.slug,
                productionOrder: completing.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setCompleting(null),
            },
        );
    };

    const submitCancel = () => {
        if (!cancelling) return;
        cancelForm.post(
            productionRoutes.cancel.url({
                tenant: tenant.slug,
                productionOrder: cancelling.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelling(null) },
        );
    };

    const columns: ColumnDef<ProductionOrder>[] = [
        {
            accessorKey: 'id',
            header: 'Order #',
            cell: ({ row }) => (
                <Link
                    href={productionRoutes.show.url({
                        tenant: tenant.slug,
                        productionOrder: row.original.id,
                    })}
                    className="font-medium text-primary tabular-nums hover:underline"
                >
                    #{row.original.id}
                </Link>
            ),
        },
        {
            accessorKey: 'product',
            header: 'Product',
            cell: ({ row }) => row.original.product,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const variant =
                    row.original.status === 'completed'
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
            accessorKey: 'quantity',
            header: 'Quantity',
            cell: ({ row }) => formatQuantity(row.original.quantity),
            meta: { className: 'text-right tabular-nums' },
        },
        {
            accessorKey: 'item_count',
            header: 'Materials',
            cell: ({ row }) => row.original.item_count,
            meta: {
                className:
                    'hidden text-right text-muted-foreground sm:table-cell',
            },
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
                                aria-label={`Actions for order #${order.id}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            {pending ? (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            completeForm.reset();
                                            completeForm.clearErrors();
                                            setCompleting(order);
                                        }}
                                    >
                                        <Factory className="size-4" />
                                        Complete
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
                { title: productionOrderMeta.plural, href: base },
            ]}
        >
            <Head title={productionOrderMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {productionOrderMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Make products from their BOM. Marking an order done uses up
                    the raw materials and adds the finished products to stock.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={orders}
                filters={filters}
                baseUrl={base}
                only={['orders', 'filters']}
                getRowId={(order) => String(order.id)}
                title={productionOrderMeta.plural}
                toolbar={
                    <Button
                        onClick={dialog.openCreate}
                        className="shrink-0"
                        disabled={products.length === 0}
                    >
                        <Plus className="size-4" />
                        New {productionOrderMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={productionOrderMeta.icon}
                        title={`No ${productionOrderMeta.plural.toLowerCase()} yet`}
                        description={
                            products.length === 0
                                ? 'Give a product a BOM first, then you can build it here.'
                                : 'Create your first production order to start manufacturing.'
                        }
                        action={
                            products.length > 0 ? (
                                <Button onClick={dialog.openCreate}>
                                    <Plus className="size-4" />
                                    New {productionOrderMeta.singular}
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
                entityLabel={productionOrderMeta.singular}
                baseUrl={base}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-lg"
                description={{
                    create: "Pick a product and how many to build. The BOM is saved with the order, so changing it later won't affect this one.",
                    edit: 'Update this production order.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value={productId}
                                />
                                <ComboboxField
                                    id="product"
                                    label="Product"
                                    options={productOptions}
                                    value={productId}
                                    onChange={setProductId}
                                    error={errors.product_id}
                                    placeholder="Select product"
                                    searchPlaceholder="Search products…"
                                    emptyText="No products can be manufactured yet."
                                />
                            </div>
                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="quantity"
                                    hint="How many finished units to build. The raw materials needed come from the product's BOM, multiplied by this number."
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
                                    aria-invalid={!!errors.quantity}
                                />
                            </div>
                        </div>

                        {previewBom.length > 0 ? (
                            <div className="space-y-2 rounded-md border bg-muted/40 p-3">
                                <p className="font-medium text-sm">
                                    Materials consumed on completion
                                </p>
                                <ul className="space-y-1 text-sm">
                                    {previewBom.map((line) => (
                                        <li
                                            key={line.name}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span className="text-muted-foreground">
                                                {line.name}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatQuantity(line.quantity)}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    × {formatQuantity(buildQty)}{' '}
                                                    ={' '}
                                                </span>
                                                <span className="font-medium">
                                                    {formatQuantity(
                                                        line.quantity *
                                                            buildQty,
                                                    )}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : null}

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

            {/* Complete dialog */}
            <Dialog
                open={completing !== null}
                onOpenChange={(next) => {
                    if (!next) setCompleting(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Complete production order #{completing?.id}
                        </DialogTitle>
                        <DialogDescription>
                            This will consume the materials and add the finished
                            “{completing?.product}” at a warehouse. If any
                            material is short there, nothing happens.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="complete-warehouse"
                        label="Warehouse"
                        hint="Where the finished units are added and the materials are consumed."
                        options={warehouseOptions}
                        value={completeForm.data.warehouse_id}
                        onChange={(value) =>
                            completeForm.setData('warehouse_id', value)
                        }
                        error={completeForm.errors.warehouse_id}
                        placeholder="Select warehouse"
                        searchPlaceholder="Search warehouses…"
                        emptyText="No warehouses found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={completeForm.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitComplete}
                            disabled={
                                completeForm.processing ||
                                !completeForm.data.warehouse_id
                            }
                        >
                            {completeForm.processing ? (
                                <>
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Completing…
                                </>
                            ) : (
                                <>
                                    <Factory className="size-4" />
                                    Complete build
                                </>
                            )}
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
                        <DialogTitle>Cancel production order</DialogTitle>
                        <DialogDescription>
                            Cancel order #{cancelling?.id}? It can no longer be
                            completed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={cancelForm.processing}
                            >
                                Keep order
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            <Ban className="size-4" />
                            Cancel order
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
