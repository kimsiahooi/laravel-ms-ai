import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Contact,
    LoaderCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import TenantLayout from '@/layouts/tenant-layout';

type Customer = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    created_at: string;
};

type PageProps = {
    customers: Paginator<Customer>;
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

export default function CustomersIndex() {
    const page = usePage();
    const { customers, filters, tenant } = page.props as unknown as PageProps;
    const base = `/${tenant.slug}/customers`;

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [notes, setNotes] = useState('');
    const [deleting, setDeleting] = useState<Customer | null>(null);

    const resetForm = () => {
        setName('');
        setEmail('');
        setPhone('');
        setAddress('');
        setNotes('');
    };

    const openCreate = () => {
        setEditing(null);
        resetForm();
        setFormOpen(true);
    };

    const openEdit = (customer: Customer) => {
        setEditing(customer);
        setName(customer.name);
        setEmail(customer.email ?? '');
        setPhone(customer.phone ?? '');
        setAddress(customer.address ?? '');
        setNotes(customer.notes ?? '');
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

    const columns: ColumnDef<Customer>[] = [
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
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Actions for ${row.original.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() => openEdit(row.original)}
                        >
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setDeleting(row.original)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Customers', href: base },
            ]}
        >
            <Head title="Customers" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Customers
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the customers who buy from your catalog.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={customers}
                filters={filters}
                baseUrl={base}
                only={['customers', 'filters']}
                getRowId={(customer) => String(customer.id)}
                title="Customers"
                searchPlaceholder="Search name or email…"
                toolbar={
                    <Button onClick={openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New customer
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Contact className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No customers yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Add your first customer to start tracking
                                    your buyers.
                                </p>
                            </div>
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                New customer
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
                            {editing ? 'Edit customer' : 'New customer'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this customer.'
                                : 'Add a customer to your catalog.'}
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
                                        placeholder="e.g. Globex Corporation"
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
                                        placeholder="e.g. buyer@globex.test"
                                        aria-invalid={!!errors.email}
                                        aria-describedby={
                                            errors.email
                                                ? 'email-error'
                                                : undefined
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
                                            errors.phone
                                                ? 'phone-error'
                                                : undefined
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
                                            errors.address
                                                ? 'address-error'
                                                : undefined
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
                                            errors.notes
                                                ? 'notes-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="notes-error"
                                        role="alert"
                                        message={errors.notes}
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
                                            'Create customer'
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
                        <DialogTitle>Delete customer</DialogTitle>
                        <DialogDescription>
                            Remove “{deleting?.name}” from your customers? This
                            removes the record from your directory.
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
