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
import { locationMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { dashboard } from '@/routes/tenant';
import locationsRoutes from '@/routes/tenant/locations';
import type { TenantPageProps } from '@/types';

type Location = App.Data.LocationData;

type PageProps = TenantPageProps & {
    locations: Paginator<Location>;
};

export default function LocationsIndex() {
    const { locations, filters, tenant } = usePageProps<PageProps>();
    const base = locationsRoutes.index.url({ tenant: tenant.slug });

    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [address, setAddress] = useState('');

    const resetForm = () => {
        setName('');
        setCode('');
        setAddress('');
    };

    const dialog = useResourceDialog<Location>({
        onCreate: resetForm,
        onEdit: (location) => {
            setName(location.name);
            setCode(location.code ?? '');
            setAddress(location.address ?? '');
        },
    });

    const del = useDelete<Location>({ baseUrl: base });

    const columns: ColumnDef<Location>[] = [
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
            header: 'Location code',
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
                { title: locationMeta.plural, href: base },
            ]}
        >
            <Head title={locationMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {locationMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Your sites and branches — each one holds warehouses.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={locations}
                filters={filters}
                baseUrl={base}
                exportResource="locations"
                only={['locations', 'filters']}
                getRowId={(location) => String(location.id)}
                title={locationMeta.plural}
                searchPlaceholder="Search name or code…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {locationMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={locationMeta.icon}
                        title="No locations yet"
                        description="Add your first site or branch to hold warehouses."
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
                description={{
                    create: 'Add a site or branch — like a factory or office. Warehouses live inside a location.',
                    edit: "Update this location's details.",
                }}
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
                                placeholder="e.g. Kuala Lumpur HQ"
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
                                hint="A short code for this site, e.g. “KL” or “PG”. It appears wherever the site is listed."
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
                                placeholder="e.g. KL"
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
                title="Delete location"
                description={
                    <>
                        Delete “{del.deleting?.name}”? A location that still has
                        warehouses can't be deleted.
                    </>
                }
            />
        </TenantLayout>
    );
}
