import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Boxes, LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TenantLayout from '@/layouts/tenant-layout';

type RawMaterial = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    min_stock: string;
    created_at: string;
};

type PageProps = {
    rawMaterials: Paginator<RawMaterial>;
    filters: { search: string; per_page: number };
    tenant: { slug: string; name: string };
    flash: { success: string | null };
};

function flashToast(page: { props: unknown }): void {
    const message = (page.props as { flash?: { success?: string | null } })
        .flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function RawMaterialsIndex() {
    const page = usePage();
    const { rawMaterials, filters, tenant } =
        page.props as unknown as PageProps;
    const base = `/${tenant.slug}/raw-materials`;

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<RawMaterial | null>(null);
    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [unit, setUnit] = useState('');
    const [minStock, setMinStock] = useState('0');
    const [deleting, setDeleting] = useState<RawMaterial | null>(null);

    const resetForm = () => {
        setName('');
        setSku('');
        setUnit('');
        setMinStock('0');
    };

    const openCreate = () => {
        setEditing(null);
        resetForm();
        setFormOpen(true);
    };

    const openEdit = (rawMaterial: RawMaterial) => {
        setEditing(rawMaterial);
        setName(rawMaterial.name);
        setSku(rawMaterial.sku);
        setUnit(rawMaterial.unit);
        setMinStock(String(Number(rawMaterial.min_stock ?? 0)));
        setFormOpen(true);
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }
        router.delete(`${base}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: (deleted) => {
                setDeleting(null);
                flashToast(deleted);
            },
        });
    };

    const columns: ColumnDef<RawMaterial>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'sku',
            header: 'SKU',
            cell: ({ row }) => (
                <span className="font-mono text-muted-foreground text-xs">
                    {row.original.sku}
                </span>
            ),
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
        },
        {
            accessorKey: 'min_stock',
            header: 'Min stock',
            cell: ({ row }) =>
                Number(row.original.min_stock).toLocaleString(undefined, {
                    maximumFractionDigits: 4,
                }),
            meta: {
                className: 'text-right text-muted-foreground tabular-nums',
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <RowActions
                    label={row.original.name}
                    onEdit={() => openEdit(row.original)}
                    onDelete={() => setDeleting(row.original)}
                />
            ),
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Raw materials', href: base },
            ]}
        >
            <Head title="Raw materials" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Raw materials
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the raw materials that feed your production.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={rawMaterials}
                filters={filters}
                baseUrl={base}
                only={['rawMaterials', 'filters']}
                getRowId={(rawMaterial) => String(rawMaterial.id)}
                title="Raw materials"
                searchPlaceholder="Search name or SKU…"
                toolbar={
                    <Button onClick={openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New raw material
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Boxes className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No raw materials yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Add your first raw material to start
                                    tracking your stock.
                                </p>
                            </div>
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                New raw material
                            </Button>
                        </CardContent>
                    </Card>
                }
            />

            {/* Create / edit dialog */}
            <Dialog
                open={formOpen}
                onOpenChange={(next) => {
                    if (!next) {
                        setFormOpen(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit raw material' : 'New raw material'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this raw material.'
                                : 'Add a raw material to your catalog.'}
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        key={editing?.id ?? 'new'}
                        action={editing ? `${base}/${editing.id}` : base}
                        method={editing ? 'put' : 'post'}
                        disableWhileProcessing
                        onSuccess={(saved) => {
                            setFormOpen(false);
                            flashToast(saved);
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        required
                                        autoFocus
                                        placeholder="e.g. Steel Rod"
                                        aria-invalid={!!errors.name}
                                        aria-describedby={
                                            errors.name
                                                ? 'name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="name-error"
                                        role="alert"
                                        message={errors.name}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="sku">SKU</Label>
                                    <Input
                                        id="sku"
                                        name="sku"
                                        value={sku}
                                        onChange={(event) =>
                                            setSku(event.target.value)
                                        }
                                        required
                                        placeholder="e.g. RM-001"
                                        aria-invalid={!!errors.sku}
                                        aria-describedby={
                                            errors.sku ? 'sku-error' : undefined
                                        }
                                    />
                                    <InputError
                                        id="sku-error"
                                        role="alert"
                                        message={errors.sku}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="unit">Unit</Label>
                                    <Input
                                        id="unit"
                                        name="unit"
                                        value={unit}
                                        onChange={(event) =>
                                            setUnit(event.target.value)
                                        }
                                        required
                                        placeholder="e.g. kg"
                                        aria-invalid={!!errors.unit}
                                        aria-describedby={
                                            errors.unit
                                                ? 'unit-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="unit-error"
                                        role="alert"
                                        message={errors.unit}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="min_stock">Min stock</Label>
                                    <Input
                                        id="min_stock"
                                        name="min_stock"
                                        type="number"
                                        min={0}
                                        step="any"
                                        value={minStock}
                                        onChange={(event) =>
                                            setMinStock(event.target.value)
                                        }
                                        placeholder="0"
                                        aria-invalid={!!errors.min_stock}
                                        aria-describedby={
                                            errors.min_stock
                                                ? 'min_stock-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="min_stock-error"
                                        role="alert"
                                        message={errors.min_stock}
                                    />
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <>
                                                <LoaderCircle className="size-4 animate-spin" />
                                                Saving…
                                            </>
                                        ) : editing ? (
                                            'Save changes'
                                        ) : (
                                            'Create raw material'
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete raw material</DialogTitle>
                        <DialogDescription>
                            Remove “{deleting?.name}” from your catalog? This
                            can be restored later.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
