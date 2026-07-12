import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Ban,
    LoaderCircle,
    MoreHorizontal,
    PackageX,
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
import { dashboard } from '@/routes/tenant';
import returnsRoutes from '@/routes/tenant/purchase-returns';
import type { TenantPageProps } from '@/types';

type PurchaseReturn = App.Data.PurchaseReturnData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    returns: Paginator<PurchaseReturn>;
    suppliers: Option[];
    rawMaterials: Option[];
    warehouses: Option[];
};

type Line = { key: number; rawMaterialId: string; quantity: string };

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}

export default function PurchaseReturnsIndex() {
    const { returns, filters, suppliers, rawMaterials, warehouses, tenant } =
        usePageProps<PageProps>();
    const base = returnsRoutes.index.url({ tenant: tenant.slug });

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
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<Line[]>([]);
    const lineKey = useRef(0);

    const [completing, setCompleting] = useState<PurchaseReturn | null>(null);
    const [cancelling, setCancelling] = useState<PurchaseReturn | null>(null);
    const completeForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const newLine = (): Line => ({
        key: lineKey.current++,
        rawMaterialId: '',
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

    const dialog = useResourceDialog<PurchaseReturn>({
        onCreate: () => {
            setSupplierId('');
            setNotes('');
            setLines([newLine()]);
        },
        onEdit: (ret) => {
            setSupplierId('');
            setNotes('');
            setLines(
                ret.items.map((item) => ({
                    key: lineKey.current++,
                    rawMaterialId: item.raw_material_id
                        ? String(item.raw_material_id)
                        : '',
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
                purchaseReturn: completing.id,
            }),
            { preserveScroll: true, onSuccess: () => setCompleting(null) },
        );
    };

    const submitCancel = () => {
        if (!cancelling) return;
        cancelForm.post(
            returnsRoutes.cancel.url({
                tenant: tenant.slug,
                purchaseReturn: cancelling.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelling(null) },
        );
    };

    const columns: ColumnDef<PurchaseReturn>[] = [
        {
            accessorKey: 'id',
            header: 'Return #',
            cell: ({ row }) => (
                <Link
                    href={returnsRoutes.show.url({
                        tenant: tenant.slug,
                        purchaseReturn: row.original.id,
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
                                        <PackageX className="size-4" />
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
                { title: 'Purchase returns', href: base },
            ]}
        >
            <Head title="Purchase returns" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Purchase returns
                </h1>
                <p className="text-muted-foreground text-sm">
                    Send received raw materials back to a supplier. Completing a
                    return removes the stock from a warehouse.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={returns}
                filters={filters}
                baseUrl={base}
                only={['returns', 'filters']}
                getRowId={(ret) => String(ret.id)}
                title="Purchase returns"
                toolbar={
                    <Button
                        onClick={dialog.openCreate}
                        className="shrink-0"
                        disabled={rawMaterials.length === 0}
                    >
                        <Plus className="size-4" />
                        New return
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={Undo2}
                        title="No purchase returns yet"
                        description={
                            rawMaterials.length === 0
                                ? 'Add a raw material first, then you can return it here.'
                                : 'Create a return to send raw materials back to a supplier.'
                        }
                        action={
                            rawMaterials.length > 0 ? (
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
                entityLabel="purchase return"
                baseUrl={base}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                description={{
                    create: 'Return raw materials to a supplier.',
                    edit: 'Update this pending return.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="sm:max-w-sm">
                            <input
                                type="hidden"
                                name="supplier_id"
                                value={supplierId}
                            />
                            <ComboboxField
                                id="supplier"
                                label="Supplier"
                                hint="Who you're returning to (optional)."
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
                                            placeholder="Select material"
                                            searchPlaceholder="Search materials…"
                                            emptyText="No raw materials found."
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
                            Each line will be removed from your inventory at the
                            selected warehouse.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="complete-warehouse"
                        label="Return from"
                        hint="The warehouse the returned stock leaves from."
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
                        <DialogTitle>Cancel purchase return</DialogTitle>
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
