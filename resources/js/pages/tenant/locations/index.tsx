import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { locationMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { dashboard } from '@/routes/tenant';
import locationsRoutes from '@/routes/tenant/locations';
import type { TenantPageProps } from '@/types';

type Location = App.Data.LocationData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    locations: Paginator<Location>;
    warehouses: Option[];
};

export default function LocationsIndex() {
    const { locations, filters, warehouses, tenant } =
        usePageProps<PageProps>();
    const base = locationsRoutes.index.url({ tenant: tenant.slug });

    const warehouseOptions = warehouses.map((w) => ({
        value: String(w.id),
        label: w.name,
    }));

    const [warehouseId, setWarehouseId] = useState('');
    const [code, setCode] = useState('');
    const [name, setName] = useState('');

    const resetForm = () => {
        setWarehouseId('');
        setCode('');
        setName('');
    };

    const fillForm = (location: Location) => {
        const whId = location.warehouse_id ? String(location.warehouse_id) : '';
        setWarehouseId(
            warehouseOptions.some((o) => o.value === whId) ? whId : '',
        );
        setCode(location.code);
        setName(location.name ?? '');
    };

    const dialog = useResourceDialog<Location>({
        onCreate: resetForm,
        onEdit: fillForm,
    });

    const del = useDelete<Location>({ baseUrl: base });

    const columns: ColumnDef<Location>[] = [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono text-foreground text-xs">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => row.original.name ?? '—',
        },
        {
            accessorKey: 'warehouse',
            header: 'Warehouse',
            cell: ({ row }) => row.original.warehouse ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <RowActions
                    label={row.original.code}
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
                { title: locationMeta.plural, href: base },
            ]}
        >
            <Head title={locationMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {locationMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the storage locations inside your warehouses.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={locations}
                filters={filters}
                baseUrl={base}
                only={['locations', 'filters']}
                getRowId={(location) => String(location.id)}
                title={locationMeta.plural}
                searchPlaceholder="Search code or name…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {locationMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={locationMeta.icon}
                        title={`No ${locationMeta.plural.toLowerCase()} yet`}
                        description="Add your first location to start organizing warehouse storage."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {locationMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={locationMeta.singular}
                baseUrl={base}
            >
                {({ errors }) => (
                    <>
                        {/* Hidden input mirrors the combobox selection */}
                        <input
                            type="hidden"
                            name="warehouse_id"
                            value={warehouseId}
                        />

                        <ComboboxField
                            id="warehouse"
                            label="Warehouse"
                            options={warehouseOptions}
                            value={warehouseId}
                            onChange={setWarehouseId}
                            error={errors.warehouse_id}
                            placeholder="Select warehouse"
                            searchPlaceholder="Search warehouses…"
                            emptyText="No warehouses."
                        />
                        <div className="space-y-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                value={code}
                                onChange={(event) =>
                                    setCode(event.target.value)
                                }
                                required
                                autoFocus
                                placeholder="e.g. A-01"
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
                            <Label htmlFor="name">
                                Name{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                placeholder="e.g. Aisle 1, Shelf B"
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
                title="Delete location"
                description={
                    <>
                        Remove “{del.deleting?.code}” from your inventory? This
                        can be restored later.
                    </>
                }
            />
        </TenantLayout>
    );
}
