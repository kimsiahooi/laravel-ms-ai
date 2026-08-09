import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { customerMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate } from '@/lib/format';
import { customerSchema } from '@/lib/validation/schemas/customer';
import { dashboard } from '@/routes/tenant';
import customersRoutes from '@/routes/tenant/customers';
import type { TenantPageProps } from '@/types';

type Customer = App.Data.CustomerData;
/** A country the address may use, as App\Support\Countries lists them. */
type CountryOption = { value: string; label: string };

type PageProps = TenantPageProps & {
    customers: Paginator<Customer>;
    /** The country codes a customer address may use (App\Support\Countries). */
    countries: CountryOption[];
};

export default function CustomersIndex() {
    const { customers, filters, tenant, countries } = usePageProps<PageProps>();
    // Built from the server's own list, so the two can't drift apart.
    const schema = useMemo(
        () => customerSchema(countries.map((country) => country.value)),
        [countries],
    );
    const { can } = usePermissions();
    const base = customersRoutes.index.url({ tenant: tenant.slug });

    const canCreate = can('customers.create');
    const canEdit = can('customers.update');
    const canDelete = can('customers.delete');

    const [name, setName] = useState('');
    const [contactPerson, setContactPerson] = useState('');
    const [email, setEmail] = useState('');
    const [tin, setTin] = useState('');
    const [registrationNo, setRegistrationNo] = useState('');
    const [sstRegistrationNo, setSstRegistrationNo] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [city, setCity] = useState('');
    const [postcode, setPostcode] = useState('');
    const [stateCode, setStateCode] = useState('');
    const [countryCode, setCountryCode] = useState('');
    const [notes, setNotes] = useState('');

    const resetForm = () => {
        setName('');
        setContactPerson('');
        setEmail('');
        setTin('');
        setRegistrationNo('');
        setSstRegistrationNo('');
        setPhone('');
        setAddress('');
        setCity('');
        setPostcode('');
        setStateCode('');
        setCountryCode('');
        setNotes('');
    };

    const dialog = useResourceDialog<Customer>({
        onCreate: resetForm,
        onEdit: (customer) => {
            setName(customer.name);
            setContactPerson(customer.contact_person ?? '');
            setEmail(customer.email ?? '');
            setTin(customer.tin ?? '');
            setRegistrationNo(customer.registration_no ?? '');
            setSstRegistrationNo(customer.sst_registration_no ?? '');
            setPhone(customer.phone ?? '');
            setAddress(customer.address ?? '');
            setCity(customer.city ?? '');
            setPostcode(customer.postcode ?? '');
            setStateCode(customer.state_code ?? '');
            setCountryCode(customer.country_code ?? '');
            setNotes(customer.notes ?? '');
        },
    });

    const del = useDelete<Customer>({
        baseUrl: base,
    });

    const columns: ColumnDef<Customer>[] = [
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
            accessorKey: 'email',
            header: 'Email',
            cell: ({ row }) => row.original.email ?? '—',
            meta: {
                className:
                    'hidden max-w-md truncate text-muted-foreground sm:table-cell',
                sortKey: 'email',
            },
        },
        {
            accessorKey: 'phone',
            header: 'Phone',
            cell: ({ row }) => row.original.phone ?? '—',
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
                { title: customerMeta.plural, href: base },
            ]}
        >
            <Head title={customerMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {customerMeta.plural}
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
                exportResource="customers"
                only={['customers', 'filters']}
                getRowId={(customer) => String(customer.id)}
                title={customerMeta.plural}
                searchPlaceholder="Search name, email, or notes…"
                toolbar={
                    canCreate ? (
                        <Button
                            onClick={dialog.openCreate}
                            className="shrink-0"
                        >
                            <Plus className="size-4" />
                            New {customerMeta.singular}
                        </Button>
                    ) : undefined
                }
                emptyState={
                    <EmptyState
                        icon={customerMeta.icon}
                        title={`No ${customerMeta.plural.toLowerCase()} yet`}
                        description="Add your first customer to start tracking your buyers."
                        action={
                            canCreate ? (
                                <Button onClick={dialog.openCreate}>
                                    <Plus className="size-4" />
                                    New {customerMeta.singular}
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
                entityLabel={customerMeta.singular}
                baseUrl={base}
                description={{
                    create: 'Add a business or person you sell to. They can be picked when you create a sales order.',
                    edit: "Update this customer's details.",
                }}
                schema={schema}
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
                                placeholder="e.g. Globex Corporation"
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
                            <Label htmlFor="contact_person">
                                Contact person{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="contact_person"
                                name="contact_person"
                                value={contactPerson}
                                onChange={(event) =>
                                    setContactPerson(event.target.value)
                                }
                                placeholder="e.g. Jane Tan"
                                aria-invalid={!!errors.contact_person}
                                aria-describedby={
                                    errors.contact_person
                                        ? 'contact_person-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="contact_person-error"
                                role="alert"
                                message={errors.contact_person}
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
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <InputError
                                id="email-error"
                                role="alert"
                                message={errors.email}
                            />
                        </div>
                        <div className="space-y-3 rounded-lg border border-border p-3">
                            <p className="font-medium text-foreground text-sm">
                                Tax &amp; e-invoice details{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Used to build an e-invoice-ready document
                                (MyInvois / InvoiceNow). Fill these for business
                                customers.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="tin">TIN</Label>
                                    <Input
                                        id="tin"
                                        name="tin"
                                        value={tin}
                                        onChange={(event) =>
                                            setTin(event.target.value)
                                        }
                                        placeholder="Tax Identification No."
                                        aria-invalid={!!errors.tin}
                                        aria-describedby={
                                            errors.tin ? 'tin-error' : undefined
                                        }
                                    />
                                    <InputError
                                        id="tin-error"
                                        role="alert"
                                        message={errors.tin}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="registration_no">
                                        Registration no.
                                    </Label>
                                    <Input
                                        id="registration_no"
                                        name="registration_no"
                                        value={registrationNo}
                                        onChange={(event) =>
                                            setRegistrationNo(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="SSM (MY) / UEN (SG)"
                                        aria-invalid={!!errors.registration_no}
                                        aria-describedby={
                                            errors.registration_no
                                                ? 'registration_no-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="registration_no-error"
                                        role="alert"
                                        message={errors.registration_no}
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="sst_registration_no">
                                        SST / GST registration no.
                                    </Label>
                                    <Input
                                        id="sst_registration_no"
                                        name="sst_registration_no"
                                        value={sstRegistrationNo}
                                        onChange={(event) =>
                                            setSstRegistrationNo(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="If tax-registered"
                                        aria-invalid={
                                            !!errors.sst_registration_no
                                        }
                                        aria-describedby={
                                            errors.sst_registration_no
                                                ? 'sst_registration_no-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="sst_registration_no-error"
                                        role="alert"
                                        message={errors.sst_registration_no}
                                    />
                                </div>
                            </div>
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
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="city">
                                    City{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="city"
                                    name="city"
                                    value={city}
                                    onChange={(event) =>
                                        setCity(event.target.value)
                                    }
                                    placeholder="e.g. Kuala Lumpur"
                                    aria-invalid={!!errors.city}
                                    aria-describedby={
                                        errors.city ? 'city-error' : undefined
                                    }
                                />
                                <InputError
                                    id="city-error"
                                    role="alert"
                                    message={errors.city}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="postcode">
                                    Postcode{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="postcode"
                                    name="postcode"
                                    value={postcode}
                                    onChange={(event) =>
                                        setPostcode(event.target.value)
                                    }
                                    placeholder="e.g. 50000"
                                    aria-invalid={!!errors.postcode}
                                    aria-describedby={
                                        errors.postcode
                                            ? 'postcode-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="postcode-error"
                                    role="alert"
                                    message={errors.postcode}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="state_code">
                                    State code{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="state_code"
                                    name="state_code"
                                    value={stateCode}
                                    onChange={(event) =>
                                        setStateCode(event.target.value)
                                    }
                                    placeholder="e.g. 14 (WP KL)"
                                    aria-invalid={!!errors.state_code}
                                    aria-describedby={
                                        errors.state_code
                                            ? 'state_code-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="state_code-error"
                                    role="alert"
                                    message={errors.state_code}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="country_code">
                                    Country{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                {/* A picker, not free text: only these codes are
                                    accepted, and an e-invoice is built from this. */}
                                <NativeSelect
                                    id="country_code"
                                    name="country_code"
                                    value={countryCode}
                                    onChange={(event) =>
                                        setCountryCode(event.target.value)
                                    }
                                    aria-invalid={!!errors.country_code}
                                    aria-describedby={
                                        errors.country_code
                                            ? 'country_code-error'
                                            : undefined
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Not set
                                    </NativeSelectOption>
                                    {countries.map((country) => (
                                        <NativeSelectOption
                                            key={country.value}
                                            value={country.value}
                                        >
                                            {country.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError
                                    id="country_code-error"
                                    role="alert"
                                    message={errors.country_code}
                                />
                            </div>
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
                title="Delete customer"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your customers? This
                        will delete their contact details.
                    </>
                }
            />
        </TenantLayout>
    );
}
