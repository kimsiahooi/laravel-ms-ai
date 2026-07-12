import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Ban,
    LoaderCircle,
    MoreHorizontal,
    PackagePlus,
    Pencil,
    Plus,
    Trash2,
    Undo2,
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
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { dashboard } from '@/routes/tenant';
import returnsRoutes from '@/routes/tenant/sales-returns';
import type { TenantPageProps } from '@/types';

type SalesReturn = App.Data.SalesReturnData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    returns: Paginator<SalesReturn>;
    customers: Option[];
    products: Option[];
    warehouses: Option[];
};

type Line = { key: number; productId: string; quantity: string };

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}

export default function SalesReturnsIndex() {
    const { returns, filters, customers, products, warehouses, tenant } =
        usePageProps<PageProps>();
    const base = returnsRoutes.index.url({ tenant: tenant.slug });

    const customerOptions = toOptions(customers);
    const productOptions = toOptions(products);
    const warehouseOptions = toOptions(warehouses);

    const [customerId, setCustomerId] = useState('');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<Line[]>([]);
    const lineKey = useRef(0);

    const [completing, setCompleting] = useState<SalesReturn | null>(null);
    const [cancelling, setCancelling] = useState<SalesReturn | null>(null);
    const completeForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const newLine = (): Line => ({
        key: lineKey.current++,
        productId: '',
        quantity: '',
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

    const dialog = useResourceDialog<SalesReturn>({
        onCreate: () => {
            setCustomerId('');
            setNotes('');
            setLines([newLine()]);
        },
        onEdit: (ret) => {
            setCustomerId('');
            setNotes('');
            setLines(
                ret.items.map((item) => ({
                    key: lineKey.current++,
                    productId: item.product_id ? String(item.product_id) : '',
                    quantity: String(item.quantity),
                })),
            );
        },
    });

    const submitComplete = () => {
        if (!completing) return;
        completeForm.post(
            returnsRoutes.complete.url({
                tenant: tenant.slug,
                salesReturn: completing.id,
            }),
            { preserveScroll: true, onSuccess: () => setCompleting(null) },
        );
    };

    const submitCancel = () => {
        if (!cancelling) return;
        cancelForm.post(
            returnsRoutes.cancel.url({
                tenant: tenant.slug,
                salesReturn: cancelling.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelling(null) },
        );
    };

    const columns: ColumnDef<SalesReturn>[] = [
        {
            accessorKey: 'id',
            header: 'Return #',
            cell: ({ row }) => (
                <Link
                    href={returnsRoutes.show.url({
                        tenant: tenant.slug,
                        salesReturn: row.original.id,
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
            cell: ({ row }) => (
                <Badge variant={statusVariant(row.original.status)}>
                    {row.original.status_label}
                </Badge>
            ),
        },
        {
            accessorKey: 'total_quantity',
            header: 'Quantity',
            cell: ({ row }) => formatQuantity(row.original.total_quantity),
            meta: { className: 'text-right tabular-nums' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => {
                const ret = row.original;
                const pending = ret.status === 'pending';

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={`Actions for return #${ret.id}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            {pending ? (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => dialog.openEdit(ret)}
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            completeForm.reset();
                                            completeForm.clearErrors();
                                            setCompleting(ret);
                                        }}
                                    >
                                        <PackagePlus className="size-4" />
                                        Complete
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => setCancelling(ret)}
                                    >
                                        <Ban className="size-4" />
                                        Cancel
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        router.delete(`${base}/${ret.id}`, {
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
                { title: 'Sales returns', href: base },
            ]}
        >
            <Head title="Sales returns" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Sales returns
                </h1>
                <p className="text-muted-foreground text-sm">
                    Take products back from a customer. Completing a return adds
                    the stock into a warehouse.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={returns}
                filters={filters}
                baseUrl={base}
                only={['returns', 'filters']}
                getRowId={(ret) => String(ret.id)}
                title="Sales returns"
                toolbar={
                    <Button
                        onClick={dialog.openCreate}
                        className="shrink-0"
                        disabled={products.length === 0}
                    >
                        <Plus className="size-4" />
                        New return
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={Undo2}
                        title="No sales returns yet"
                        description={
                            products.length === 0
                                ? 'Add a product first, then you can log a return here.'
                                : 'Create a return to take products back from a customer.'
                        }
                        action={
                            products.length > 0 ? (
                                <Button onClick={dialog.openCreate}>
                                    <Plus className="size-4" />
                                    New return
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
                entityLabel="sales return"
                baseUrl={base}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                description={{
                    create: 'Log products a customer returned.',
                    edit: 'Update this pending return.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="sm:max-w-sm">
                            <input
                                type="hidden"
                                name="customer_id"
                                value={customerId}
                            />
                            <ComboboxField
                                id="customer"
                                label="Customer"
                                hint="Who returned these (optional)."
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
                            <Label>Line items</Label>
                            {errors.items ? (
                                <p className="text-destructive text-sm">
                                    {errors.items}
                                </p>
                            ) : null}

                            <div className="space-y-3">
                                {lines.map((line, index) => (
                                    <div
                                        key={line.key}
                                        className="grid grid-cols-[1fr_auto_auto] items-end gap-2"
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
                                            placeholder="Select material"
                                            searchPlaceholder="Search materials…"
                                            emptyText="No products found."
                                        />
                                        <div className="w-28 space-y-2">
                                            <Label
                                                htmlFor={`qty-${line.key}`}
                                                className="text-muted-foreground text-xs"
                                            >
                                                Quantity
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
                            Complete return #{completing?.id}
                        </DialogTitle>
                        <DialogDescription>
                            Each line will be added to your inventory at the
                            selected warehouse.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="complete-warehouse"
                        label="Return into"
                        hint="The warehouse the returned stock arrives into."
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
                                'Complete return'
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
                        <DialogTitle>Cancel sales return</DialogTitle>
                        <DialogDescription>
                            Cancel return #{cancelling?.id}? It can no longer be
                            completed or edited.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={cancelForm.processing}
                            >
                                Keep return
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            <Ban className="size-4" />
                            Cancel return
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
