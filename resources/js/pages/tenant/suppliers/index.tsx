import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { supplierMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { dashboard } from '@/routes/tenant';
import suppliersRoutes from '@/routes/tenant/suppliers';
import type { TenantPageProps } from '@/types';

type Supplier = App.Data.SupplierData;

type PageProps = TenantPageProps & {
    suppliers: Paginator<Supplier>;
};

export default function SuppliersIndex() {
    const { suppliers, filters, tenant } = usePageProps<PageProps>();
    const base = suppliersRoutes.index.url({ tenant: tenant.slug });

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [notes, setNotes] = useState('');

    const resetForm = () => {
        setName('');
        setEmail('');
        setPhone('');
        setAddress('');
        setNotes('');
    };

    const dialog = useResourceDialog<Supplier>({
        onCreate: resetForm,
        onEdit: (supplier) => {
            setName(supplier.name);
            setEmail(supplier.email ?? '');
            setPhone(supplier.phone ?? '');
            setAddress(supplier.address ?? '');
            setNotes(supplier.notes ?? '');
        },
    });

    const del = useDelete<Supplier>({
        baseUrl: base,
    });

    const columns: ColumnDef<Supplier>[] = [
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
            accessorKey: 'email',
            header: 'Email',
            cell: ({ row }) => row.original.email ?? '—',
            meta: {
                className:
                    'hidden max-w-md truncate text-muted-foreground sm:table-cell',
            },
        },
        {
            accessorKey: 'phone',
            header: 'Phone',
            cell: ({ row }) => row.original.phone ?? '—',
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
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
                { title: supplierMeta.plural, href: base },
            ]}
        >
            <Head title={supplierMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {supplierMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the vendors that supply your catalog.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={suppliers}
                filters={filters}
                baseUrl={base}
                only={['suppliers', 'filters']}
                getRowId={(supplier) => String(supplier.id)}
                title={supplierMeta.plural}
                searchPlaceholder="Search by name or email…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {supplierMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={supplierMeta.icon}
                        title={`No ${supplierMeta.plural.toLowerCase()} yet`}
                        description="Add your first supplier to start tracking your vendors."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {supplierMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={supplierMeta.singular}
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
                                placeholder="e.g. Acme Metals"
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
                            <Label htmlFor="email">
                                Email{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                value={email}
                                onChange={(event) =>
                                    setEmail(event.target.value)
                                }
                                placeholder="e.g. sales@acme.test"
                                aria-invalid={!!errors.email}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <InputError
                                id="email-error"
                                role="alert"
                                message={errors.email}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="phone">
                                Phone{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="phone"
                                name="phone"
                                value={phone}
                                onChange={(event) =>
                                    setPhone(event.target.value)
                                }
                                placeholder="e.g. +60 12-345 6789"
                                aria-invalid={!!errors.phone}
                                aria-describedby={
                                    errors.phone ? 'phone-error' : undefined
                                }
                            />
                            <InputError
                                id="phone-error"
                                role="alert"
                                message={errors.phone}
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
                                placeholder="Internal notes"
                                aria-invalid={!!errors.notes}
                                aria-describedby={
                                    errors.notes ? 'notes-error' : undefined
                                }
                            />
                            <InputError
                                id="notes-error"
                                role="alert"
                                message={errors.notes}
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
                title="Delete supplier"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your catalog?
                        Products keep their data but will no longer be linked to
                        this supplier.
                    </>
                }
            />
        </TenantLayout>
    );
}
