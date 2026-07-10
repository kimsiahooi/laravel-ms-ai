import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rawMaterialMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import rawMaterialsRoutes from '@/routes/tenant/raw-materials';
import type { TenantPageProps } from '@/types';

type RawMaterial = App.Data.RawMaterialData;

type PageProps = TenantPageProps & {
    rawMaterials: Paginator<RawMaterial>;
};

export default function RawMaterialsIndex() {
    const { rawMaterials, filters, tenant } = usePageProps<PageProps>();
    const base = rawMaterialsRoutes.index.url({ tenant: tenant.slug });

    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [unit, setUnit] = useState('');
    const [minStock, setMinStock] = useState('0');

    const dialog = useResourceDialog<RawMaterial>({
        onCreate: () => {
            setName('');
            setSku('');
            setUnit('');
            setMinStock('0');
        },
        onEdit: (rawMaterial) => {
            setName(rawMaterial.name);
            setSku(rawMaterial.sku);
            setUnit(rawMaterial.unit);
            setMinStock(String(Number(rawMaterial.min_stock ?? 0)));
        },
    });

    const del = useDelete<RawMaterial>({
        baseUrl: base,
    });

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
            cell: ({ row }) => formatQuantity(row.original.min_stock),
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
                    onEdit={() => dialog.openEdit(row.original)}
                    onDelete={() => del.request(row.original)}
                />
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
                { title: rawMaterialMeta.plural, href: base },
            ]}
        >
            <Head title={rawMaterialMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {rawMaterialMeta.plural}
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
                title={rawMaterialMeta.plural}
                searchPlaceholder="Search name or SKU…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {rawMaterialMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={rawMaterialMeta.icon}
                        title={`No ${rawMaterialMeta.plural.toLowerCase()} yet`}
                        description="Add your first raw material to start tracking your stock."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {rawMaterialMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={rawMaterialMeta.singular}
                baseUrl={base}
            >
                {({ errors }) => (
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
                                    errors.name ? 'name-error' : undefined
                                }
                            />
                            <InputError
                                id="name-error"
                                role="alert"
                                message={errors.name}
                            />
                        </div>
                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="sku"
                                hint="A unique code you assign to identify this material — it appears on orders and stock lists."
                            >
                                SKU
                            </FieldLabel>
                            <Input
                                id="sku"
                                name="sku"
                                value={sku}
                                onChange={(event) => setSku(event.target.value)}
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
                            <FieldLabel
                                htmlFor="unit"
                                hint="The unit you count this material in, such as “kg”, “L”, or “ea”. It's shown wherever quantities appear."
                            >
                                Unit
                            </FieldLabel>
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
                                    errors.unit ? 'unit-error' : undefined
                                }
                            />
                            <InputError
                                id="unit-error"
                                role="alert"
                                message={errors.unit}
                            />
                        </div>
                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="min_stock"
                                hint="We flag this material as low stock once its on-hand quantity drops to or below this number. Set it to 0 to never flag it."
                            >
                                Min stock
                            </FieldLabel>
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
                    </>
                )}
            </ResourceFormDialog>

            <ConfirmDeleteDialog
                item={del.deleting}
                onOpenChange={(next) => {
                    if (!next) {
                        del.cancel();
                    }
                }}
                onConfirm={del.confirm}
                title="Delete raw material"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your catalog? This
                        can be restored later.
                    </>
                }
            />
        </TenantLayout>
    );
}
