import { Head, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, Truck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDelete } from '@/hooks/use-delete';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';

type Supplier = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    created_at: string;
};

type PageProps = {
    suppliers: Paginator<Supplier>;
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

export default function SuppliersIndex() {
    const page = usePage();
    const { suppliers, filters, tenant } = page.props as unknown as PageProps;
    const base = `/${tenant.slug}/suppliers`;

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
        onDeleted: flashToast,
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
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Suppliers', href: base },
            ]}
        >
            <Head title="Suppliers" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Suppliers
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
                title="Suppliers"
                searchPlaceholder="Search name or email…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New supplier
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Truck className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No suppliers yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Add your first supplier to start tracking
                                    your vendors.
                                </p>
                            </div>
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New supplier
                            </Button>
                        </CardContent>
                    </Card>
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel="supplier"
                baseUrl={base}
                onSuccess={flashToast}
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
                        Products keep their data but lose this supplier.
                    </>
                }
            />
        </TenantLayout>
    );
}
