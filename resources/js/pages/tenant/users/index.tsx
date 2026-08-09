import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Eye,
    EyeOff,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    UserMinus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { userMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate } from '@/lib/format';
import { userSchema } from '@/lib/validation/schemas/user';
import { dashboard } from '@/routes/tenant';
import usersRoutes from '@/routes/tenant/users';
import type { TenantPageProps } from '@/types';

type User = App.Data.UserData;

type PageProps = TenantPageProps & {
    users: Paginator<User>;
    /** The role names an admin can assign, for the picker. */
    roles: string[];
};

/**
 * The per-row menu. Deactivated people can only be reactivated; active people can
 * be edited, and deactivated (except yourself — you can't lock yourself out).
 * Actions are also hidden when the admin lacks the matching permission.
 */
function UserRowActions({
    user,
    canEdit,
    canDeactivate,
    onEdit,
    onDeactivate,
    onReactivate,
}: {
    user: User;
    canEdit: boolean;
    canDeactivate: boolean;
    onEdit: () => void;
    onDeactivate: () => void;
    onReactivate: () => void;
}) {
    const showEdit = user.is_active && canEdit;
    const showDeactivate = user.is_active && !user.is_self && canDeactivate;
    const showReactivate = !user.is_active && canEdit;

    // Nothing actionable (e.g. your own deactivated-impossible row) → no menu.
    if (!showEdit && !showDeactivate && !showReactivate) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={`Actions for ${user.name}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {showEdit ? (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>
                ) : null}
                {showReactivate ? (
                    <DropdownMenuItem onSelect={onReactivate}>
                        <RotateCcw className="size-4" />
                        Reactivate
                    </DropdownMenuItem>
                ) : null}
                {showDeactivate ? (
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={onDeactivate}
                    >
                        <UserMinus className="size-4" />
                        Deactivate
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function UsersIndex() {
    const { users, roles, filters, tenant } = usePageProps<PageProps>();
    const { can } = usePermissions();
    const base = usersRoutes.index.url({ tenant: tenant.slug });

    const canCreate = can('users.create');
    const canEdit = can('users.update');
    const canDeactivate = can('users.delete');

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [role, setRole] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);

    const dialog = useResourceDialog<User>({
        onCreate: () => {
            setName('');
            setEmail('');
            setRole(roles[0] ?? '');
            setPassword('');
            setShowPassword(false);
        },
        onEdit: (user) => {
            setName(user.name);
            setEmail(user.email);
            setRole(user.role ?? roles[0] ?? '');
            setPassword('');
            setShowPassword(false);
        },
    });

    // Editing leaves the password blank to keep the current one.
    const schema = useMemo(
        () => userSchema(dialog.editing !== null),
        [dialog.editing],
    );

    // Deactivate reuses the shared delete flow (DELETE /users/{id} soft-deletes).
    const del = useDelete<User>({ baseUrl: base });

    const reactivate = (user: User) => {
        router.post(
            usersRoutes.restore.url({ tenant: tenant.slug, user: user.id }),
            {},
            { preserveScroll: true },
        );
    };

    const columns: ColumnDef<User>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span
                        className={
                            row.original.is_active
                                ? 'font-medium text-foreground'
                                : 'font-medium text-muted-foreground'
                        }
                    >
                        {row.original.name}
                    </span>
                    {row.original.is_self ? (
                        <Badge
                            variant="outline"
                            className="text-muted-foreground"
                        >
                            You
                        </Badge>
                    ) : null}
                </div>
            ),
            meta: { sortKey: 'name' },
        },
        {
            accessorKey: 'email',
            header: 'Email',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.email}
                </span>
            ),
            meta: {
                className: 'hidden max-w-xs truncate sm:table-cell',
                sortKey: 'email',
            },
        },
        {
            accessorKey: 'role',
            header: 'Role',
            cell: ({ row }) =>
                row.original.role ? (
                    <Badge variant="secondary">{row.original.role}</Badge>
                ) : (
                    <span className="text-muted-foreground">No role</span>
                ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: ({ row }) =>
                row.original.is_active ? (
                    <span className="inline-flex items-center gap-1.5 text-foreground text-sm">
                        <span
                            className="size-1.5 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        Active
                    </span>
                ) : (
                    <span className="text-muted-foreground text-sm">
                        Deactivated
                    </span>
                ),
            meta: { className: 'hidden md:table-cell' },
        },
        {
            accessorKey: 'created_at',
            header: 'Added',
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
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <UserRowActions
                    user={row.original}
                    canEdit={canEdit}
                    canDeactivate={canDeactivate}
                    onEdit={() => dialog.openEdit(row.original)}
                    onDeactivate={() => del.request(row.original)}
                    onReactivate={() => reactivate(row.original)}
                />
            ),
        },
    ];

    const newUserButton = canCreate ? (
        <Button onClick={dialog.openCreate} className="shrink-0">
            <Plus className="size-4" />
            New {userMeta.singular}
        </Button>
    ) : undefined;

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: userMeta.plural, href: base },
            ]}
        >
            <Head title={userMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {userMeta.plural}
                </h1>
                <p className="text-muted-foreground text-sm">
                    Add your team and give each person a role that decides what
                    they can see and do.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={users}
                filters={filters}
                baseUrl={base}
                only={['users', 'filters']}
                getRowId={(user) => String(user.id)}
                title={userMeta.plural}
                searchPlaceholder="Search by name or email…"
                toolbar={newUserButton}
                emptyState={
                    <EmptyState
                        icon={userMeta.icon}
                        title="It's just you so far"
                        description="Add your team and give each person a role so they can sign in with their own account."
                        action={newUserButton}
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={userMeta.singular}
                baseUrl={base}
                description={{
                    create: 'Create a sign-in for someone on your team and choose what they can do.',
                    edit: "Update this person's details or change their role.",
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
                                placeholder="e.g. Jane Tan"
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
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                value={email}
                                onChange={(event) =>
                                    setEmail(event.target.value)
                                }
                                required
                                placeholder="e.g. jane@yourcompany.com"
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
                            <Label htmlFor="role">Role</Label>
                            <NativeSelect
                                id="role"
                                name="role"
                                value={role}
                                onChange={(event) =>
                                    setRole(event.target.value)
                                }
                                required
                                className="w-full"
                                aria-invalid={!!errors.role}
                                aria-describedby={
                                    errors.role ? 'role-error' : 'role-hint'
                                }
                            >
                                {roles.length === 0 ? (
                                    <NativeSelectOption value="" disabled>
                                        No roles yet — create one first
                                    </NativeSelectOption>
                                ) : null}
                                {roles.map((roleName) => (
                                    <NativeSelectOption
                                        key={roleName}
                                        value={roleName}
                                    >
                                        {roleName}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.role ? (
                                <InputError
                                    id="role-error"
                                    role="alert"
                                    message={errors.role}
                                />
                            ) : (
                                <p
                                    id="role-hint"
                                    className="text-muted-foreground text-xs"
                                >
                                    Controls what this person can see and do.
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">
                                Password{' '}
                                {dialog.editing ? (
                                    <span className="font-normal text-muted-foreground">
                                        (leave blank to keep current)
                                    </span>
                                ) : null}
                            </Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    required={!dialog.editing}
                                    autoComplete="new-password"
                                    className="pr-10"
                                    placeholder={
                                        dialog.editing
                                            ? '••••••••'
                                            : 'At least 8 characters'
                                    }
                                    aria-invalid={!!errors.password}
                                    aria-describedby={
                                        errors.password
                                            ? 'password-error'
                                            : 'password-hint'
                                    }
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword((previous) => !previous)
                                    }
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none"
                                    aria-label={
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    }
                                >
                                    {showPassword ? (
                                        <EyeOff className="size-4" />
                                    ) : (
                                        <Eye className="size-4" />
                                    )}
                                </button>
                            </div>
                            {errors.password ? (
                                <InputError
                                    id="password-error"
                                    role="alert"
                                    message={errors.password}
                                />
                            ) : (
                                <p
                                    id="password-hint"
                                    className="text-muted-foreground text-xs"
                                >
                                    You set the first password — share it with
                                    them. They can change it after signing in.
                                </p>
                            )}
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
                confirmLabel="Deactivate"
                confirmIcon={UserMinus}
                title="Deactivate this person?"
                description={
                    <>
                        “{del.deleting?.name}” won't be able to sign in until
                        you reactivate them. Their account and history are kept.
                    </>
                }
            />
        </TenantLayout>
    );
}
