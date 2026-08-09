import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FieldLabel } from '@/components/field-label';
import { InfoHint } from '@/components/info-hint';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rawMaterialMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate, formatMoney, formatQuantity } from '@/lib/format';
import { rawMaterialSchema } from '@/lib/validation/schemas/raw-material';
import { dashboard } from '@/routes/tenant';
import rawMaterialsRoutes from '@/routes/tenant/raw-materials';
import type { TenantPageProps } from '@/types';

type RawMaterial = App.Data.RawMaterialData;

type PageProps = TenantPageProps & {
    rawMaterials: Paginator<RawMaterial>;
};

export default function RawMaterialsIndex() {
    const { rawMaterials, filters, tenant } = usePageProps<PageProps>();
    const { can } = usePermissions();
    const base = rawMaterialsRoutes.index.url({ tenant: tenant.slug });

    const canCreate = can('raw-materials.create');
    const canEdit = can('raw-materials.update');
    const canDelete = can('raw-materials.delete');

    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [barcode, setBarcode] = useState('');
    const [unit, setUnit] = useState('');

    const dialog = useResourceDialog<RawMaterial>({
        onCreate: () => {
            setName('');
            setSku('');
            setBarcode('');
            setUnit('');
        },
        onEdit: (rawMaterial) => {
            setName(rawMaterial.name);
            setSku(rawMaterial.sku);
            setBarcode(rawMaterial.barcode ?? '');
            setUnit(rawMaterial.unit);
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
            meta: { sortKey: 'name' },
        },
        {
            accessorKey: 'sku',
            header: () => (
                <>
                    SKU
                    <InfoHint>
                        A unique code you assign to identify this raw material —
                        it appears on labels, orders, and stock lists.
                    </InfoHint>
                </>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-muted-foreground text-xs">
                    {row.original.sku}
                </span>
            ),
            meta: { sortKey: 'sku' },
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            cell: ({ row }) => (
                <span
                    suppressHydrationWarning
                    className="text-muted-foreground tabular-nums"
                >
                    {formatDate(row.original.created_at)}
                </span>
            ),
            meta: {
                className: 'hidden text-muted-foreground lg:table-cell',
                sortKey: 'created_at',
            },
        },
        {
            accessorKey: 'creator',
            header: 'Created by',
            cell: ({ row }) => row.original.creator ?? '—',
            meta: { className: 'hidden text-muted-foreground xl:table-cell' },
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
                    canEdit={canEdit}
                    canDelete={canDelete}
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
                exportResource="raw-materials"
                only={['rawMaterials', 'filters']}
                getRowId={(rawMaterial) => String(rawMaterial.id)}
                title={rawMaterialMeta.plural}
                searchPlaceholder="Search name, SKU, or barcode…"
                renderExpanded={(rawMaterial) => (
                    <div className="px-4 py-3">
                        {rawMaterial.purchase_history.length > 0 ? (
                            <div className="space-y-2">
                                <p className="font-medium text-sm">
                                    Purchased from
                                    <span className="ml-1 font-normal text-muted-foreground">
                                        · received purchase orders
                                    </span>
                                </p>
                                <ul className="divide-y rounded-md border bg-background">
                                    {rawMaterial.purchase_history.map(
                                        (entry) => (
                                            <li
                                                key={entry.id}
                                                className="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 text-sm"
                                            >
                                                <span className="text-foreground">
                                                    {entry.supplier ?? '—'}
                                                    <span className="ml-2 font-normal text-muted-foreground">
                                                        PO #{entry.po_id}
                                                    </span>
                                                </span>
                                                <span className="flex items-center gap-4 text-muted-foreground tabular-nums">
                                                    <span
                                                        suppressHydrationWarning
                                                    >
                                                        {entry.received_at
                                                            ? formatDate(
                                                                  entry.received_at,
                                                              )
                                                            : '—'}
                                                    </span>
                                                    <span>
                                                        {formatQuantity(
                                                            entry.quantity,
                                                        )}{' '}
                                                        {rawMaterial.unit}
                                                    </span>
                                                    <span>
                                                        {formatMoney(
                                                            entry.unit_cost,
                                                            entry.currency,
                                                        )}
                                                    </span>
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Never purchased yet — receive a purchase order
                                that includes this material to see its supplier
                                here.
                            </p>
                        )}
                    </div>
                )}
                toolbar={
                    canCreate ? (
                        <Button
                            onClick={dialog.openCreate}
                            className="shrink-0"
                        >
                            <Plus className="size-4" />
                            New {rawMaterialMeta.singular}
                        </Button>
                    ) : undefined
                }
                emptyState={
                    <EmptyState
                        icon={rawMaterialMeta.icon}
                        title={`No ${rawMaterialMeta.plural.toLowerCase()} yet`}
                        description="Add your first raw material to start tracking your stock."
                        action={
                            canCreate ? (
                                <Button onClick={dialog.openCreate}>
                                    <Plus className="size-4" />
                                    New {rawMaterialMeta.singular}
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
                entityLabel={rawMaterialMeta.singular}
                baseUrl={base}
                schema={rawMaterialSchema}
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
                                htmlFor="barcode"
                                hint="An optional barcode or QR value you can scan to find this material in stock counts, movements, and transfers."
                            >
                                Barcode{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </FieldLabel>
                            <Input
                                id="barcode"
                                name="barcode"
                                value={barcode}
                                onChange={(event) =>
                                    setBarcode(event.target.value)
                                }
                                placeholder="Scan or type a barcode"
                                aria-invalid={!!errors.barcode}
                                aria-describedby={
                                    errors.barcode ? 'barcode-error' : undefined
                                }
                            />
                            <InputError
                                id="barcode-error"
                                role="alert"
                                message={errors.barcode}
                            />
                        </div>
                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="unit"
                                hint="The unit you count this material in, such as “kg”, “L”, or “each”. It's shown wherever quantities appear."
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
