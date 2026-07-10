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
import { Textarea } from '@/components/ui/textarea';
import { warehouseMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { dashboard } from '@/routes/tenant';
import warehousesRoutes from '@/routes/tenant/warehouses';
import type { TenantPageProps } from '@/types';

type Warehouse = App.Data.WarehouseData;

type PageProps = TenantPageProps & {
    warehouses: Paginator<Warehouse>;
};

export default function WarehousesIndex() {
    const { warehouses, filters, tenant } = usePageProps<PageProps>();
    const base = warehousesRoutes.index.url({ tenant: tenant.slug });

    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [address, setAddress] = useState('');

    const resetForm = () => {
        setName('');
        setCode('');
        setAddress('');
    };

    const dialog = useResourceDialog<Warehouse>({
        onCreate: resetForm,
        onEdit: (warehouse) => {
            setName(warehouse.name);
            setCode(warehouse.code ?? '');
            setAddress(warehouse.address ?? '');
        },
    });

    const del = useDelete<Warehouse>({
        baseUrl: base,
    });

    const columns: ColumnDef<Warehouse>[] = [
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
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) =>
                row.original.code ? (
                    <span className="font-mono text-muted-foreground text-xs">
                        {row.original.code}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            accessorKey: 'address',
            header: 'Address',
            cell: ({ row }) => row.original.address ?? '—',
            meta: {
                className:
                    'hidden max-w-md truncate text-muted-foreground md:table-cell',
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
                { title: warehouseMeta.plural, href: base },
            ]}
        >
            <Head title={warehouseMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {warehouseMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the warehouses that hold your inventory.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={warehouses}
                filters={filters}
                baseUrl={base}
                only={['warehouses', 'filters']}
                getRowId={(warehouse) => String(warehouse.id)}
                title={warehouseMeta.plural}
                searchPlaceholder="Search name or code…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {warehouseMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={warehouseMeta.icon}
                        title={`No ${warehouseMeta.plural.toLowerCase()} yet`}
                        description="Add your first warehouse to start organizing your inventory."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {warehouseMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={warehouseMeta.singular}
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
                                placeholder="e.g. Main Warehouse"
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
                                htmlFor="code"
                                hint="A short code for this warehouse, such as “KL” or “WH-1”. It appears on transfers and stock lists."
                            >
                                Code{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </FieldLabel>
                            <Input
                                id="code"
                                name="code"
                                value={code}
                                onChange={(event) =>
                                    setCode(event.target.value)
                                }
                                placeholder="e.g. WH-01"
                                aria-invalid={!!errors.code}
                                aria-describedby={
                                    errors.code ? 'code-error' : undefined
                                }
                            />
                            <InputError
                                id="code-error"
                                role="alert"
                                message={errors.code}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="address">
                                Address{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Textarea
                                id="address"
                                name="address"
                                value={address}
                                onChange={(event) =>
                                    setAddress(event.target.value)
                                }
                                placeholder="Street address"
                                aria-invalid={!!errors.address}
                                aria-describedby={
                                    errors.address ? 'address-error' : undefined
                                }
                            />
                            <InputError
                                id="address-error"
                                role="alert"
                                message={errors.address}
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
                title="Delete warehouse"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your inventory?
                        Locations and stock keep their data but lose this
                        warehouse.
                    </>
                }
            />
        </TenantLayout>
    );
}
