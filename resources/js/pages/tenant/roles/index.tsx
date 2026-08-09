import { Form, Head } from '@inertiajs/react';
import {
    LoaderCircle,
    Lock,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { roleMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import { useZodGate } from '@/hooks/use-zod-gate';
import TenantLayout from '@/layouts/tenant-layout';
import { roleSchema } from '@/lib/validation/schemas/role';
import { dashboard } from '@/routes/tenant';
import rolesRoutes from '@/routes/tenant/roles';
import type { TenantPageProps } from '@/types';

type Role = App.Data.RoleData;

type PermissionItem = { name: string; action: string; label: string };
type PermissionGroup = {
    key: string;
    label: string;
    permissions: PermissionItem[];
};

type PageProps = TenantPageProps & {
    roles: Role[];
    permissionGroups: PermissionGroup[];
};

// Plain verbs for the matrix checkboxes (the group label already names the screen).
const ACTION_VERB: Record<string, string> = {
    view: 'View',
    create: 'Create',
    update: 'Edit',
    delete: 'Delete',
};

/** Whether every / some / none of a group's permissions are selected. */
function groupState(
    group: PermissionGroup,
    selected: Set<string>,
): boolean | 'indeterminate' {
    const count = group.permissions.filter((p) => selected.has(p.name)).length;
    if (count === 0) return false;
    if (count === group.permissions.length) return true;
    return 'indeterminate';
}

/**
 * The role editor — a slide-over with the full permission matrix. Checkboxes are
 * UI only; the selected set is submitted as hidden `permissions[]` inputs so it
 * works regardless of the checkbox primitive. Keyed by role in the parent, so it
 * mounts fresh (correct initial state) each time it opens.
 */
function RoleEditorSheet({
    open,
    onOpenChange,
    editing,
    permissionGroups,
    totalPermissions,
    baseUrl,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Role | null;
    permissionGroups: PermissionGroup[];
    totalPermissions: number;
    baseUrl: string;
}) {
    const isEdit = editing !== null;
    const [name, setName] = useState(editing?.name ?? '');
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(editing?.permissions ?? []),
    );

    const toggle = (permission: string) => {
        setSelected((previous) => {
            const next = new Set(previous);
            if (next.has(permission)) {
                next.delete(permission);
            } else {
                next.add(permission);
            }
            return next;
        });
    };

    const toggleGroup = (group: PermissionGroup) => {
        setSelected((previous) => {
            const next = new Set(previous);
            const allOn = group.permissions.every((p) => next.has(p.name));
            for (const p of group.permissions) {
                if (allOn) {
                    next.delete(p.name);
                } else {
                    next.add(p.name);
                }
            }
            return next;
        });
    };

    const selectAll = () =>
        setSelected(
            new Set(
                permissionGroups.flatMap((g) =>
                    g.permissions.map((p) => p.name),
                ),
            ),
        );
    const clearAll = () => setSelected(new Set());
    const gate = useZodGate(roleSchema);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 sm:max-w-2xl">
                <SheetHeader className="border-border border-b">
                    <SheetTitle>
                        {isEdit ? `Edit ${editing.name}` : 'New role'}
                    </SheetTitle>
                    <SheetDescription>
                        Give the role a name, then tick what people with it can
                        see and do.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    {...gate}
                    noValidate
                    action={isEdit ? `${baseUrl}/${editing.id}` : baseUrl}
                    method={isEdit ? 'put' : 'post'}
                    disableWhileProcessing
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto p-4">
                                <div className="space-y-2">
                                    <Label htmlFor="role-name">Role name</Label>
                                    <Input
                                        id="role-name"
                                        name="name"
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        required
                                        autoFocus
                                        placeholder="e.g. Warehouse staff"
                                        aria-invalid={!!errors.name}
                                        aria-describedby={
                                            errors.name
                                                ? 'role-name-error'
                                                : 'role-name-hint'
                                        }
                                    />
                                    {errors.name ? (
                                        <InputError
                                            id="role-name-error"
                                            role="alert"
                                            message={errors.name}
                                        />
                                    ) : (
                                        <p
                                            id="role-name-hint"
                                            className="text-muted-foreground text-xs"
                                        >
                                            e.g. Warehouse staff, Sales, or
                                            Accountant.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-lg border border-border">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-border border-b bg-muted/40 px-4 py-3">
                                        <div className="flex flex-col">
                                            <p className="font-medium text-foreground text-sm">
                                                Permissions
                                            </p>
                                            <p className="text-muted-foreground text-xs tabular-nums">
                                                {selected.size} of{' '}
                                                {totalPermissions} selected
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={selectAll}
                                            >
                                                Select all
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={clearAll}
                                                disabled={selected.size === 0}
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </div>

                                    <ul className="divide-y divide-border">
                                        {permissionGroups.map((group) => (
                                            <li
                                                key={group.key}
                                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <p className="font-medium text-foreground text-sm">
                                                    {group.label}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                                                    {group.permissions.map(
                                                        (permission) => (
                                                            <label
                                                                key={
                                                                    permission.name
                                                                }
                                                                htmlFor={`perm-${permission.name}`}
                                                                className="flex cursor-pointer items-center gap-2"
                                                            >
                                                                <Checkbox
                                                                    id={`perm-${permission.name}`}
                                                                    checked={selected.has(
                                                                        permission.name,
                                                                    )}
                                                                    onCheckedChange={() =>
                                                                        toggle(
                                                                            permission.name,
                                                                        )
                                                                    }
                                                                    aria-label={
                                                                        permission.label
                                                                    }
                                                                />
                                                                <span className="text-muted-foreground text-sm">
                                                                    {ACTION_VERB[
                                                                        permission
                                                                            .action
                                                                    ] ??
                                                                        permission.action}
                                                                </span>
                                                            </label>
                                                        ),
                                                    )}
                                                    <label
                                                        htmlFor={`${group.key}-all`}
                                                        className="flex cursor-pointer items-center gap-2 border-border border-l pl-4 sm:pl-5"
                                                    >
                                                        <Checkbox
                                                            id={`${group.key}-all`}
                                                            checked={groupState(
                                                                group,
                                                                selected,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleGroup(
                                                                    group,
                                                                )
                                                            }
                                                            aria-label={`Full access to ${group.label}`}
                                                        />
                                                        <span className="font-medium text-foreground text-sm">
                                                            All
                                                        </span>
                                                    </label>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <InputError
                                    role="alert"
                                    message={errors.permissions}
                                />

                                {/* Submit the selected permissions with the form. */}
                                {[...selected].map((permission) => (
                                    <input
                                        key={permission}
                                        type="hidden"
                                        name="permissions[]"
                                        value={permission}
                                    />
                                ))}
                            </div>

                            <SheetFooter className="border-border border-t">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Saving…
                                        </>
                                    ) : isEdit ? (
                                        'Save changes'
                                    ) : (
                                        'Create role'
                                    )}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

export default function RolesIndex() {
    const { roles, permissionGroups, tenant } = usePageProps<PageProps>();
    const { can } = usePermissions();
    const base = rolesRoutes.index.url({ tenant: tenant.slug });

    const canCreate = can('roles.create');
    const canEdit = can('roles.update');
    const canDelete = can('roles.delete');

    const totalPermissions = permissionGroups.reduce(
        (total, group) => total + group.permissions.length,
        0,
    );

    const dialog = useResourceDialog<Role>();
    const del = useDelete<Role>({ baseUrl: base });

    const newRoleButton = canCreate ? (
        <Button onClick={dialog.openCreate} className="shrink-0">
            <Plus className="size-4" />
            New {roleMeta.singular}
        </Button>
    ) : undefined;

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: roleMeta.plural, href: base },
            ]}
        >
            <Head title={roleMeta.plural} />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {roleMeta.plural}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Decide what each person can see and do, then assign a
                        role when you add a user.
                    </p>
                </div>
                {newRoleButton}
            </div>

            {roles.length === 0 ? (
                <EmptyState
                    icon={roleMeta.icon}
                    title="No roles yet"
                    description="Create a role to decide what each person can see and do."
                    action={newRoleButton}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => {
                        const peopleLabel = `${role.user_count} ${
                            role.user_count === 1 ? 'person' : 'people'
                        }`;
                        const summary =
                            role.is_locked ||
                            role.permissions.length === totalPermissions
                                ? 'Full access'
                                : `${role.permissions.length} of ${totalPermissions} permissions`;
                        const inUse = role.user_count > 0;

                        return (
                            <div
                                key={role.id}
                                className="flex flex-col rounded-xl border border-border bg-card p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <ShieldCheck className="size-5" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-foreground">
                                                {role.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {peopleLabel}
                                            </p>
                                        </div>
                                    </div>
                                    {role.is_locked ? (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 text-muted-foreground"
                                        >
                                            <Lock className="size-3" />
                                            Built-in
                                        </Badge>
                                    ) : null}
                                </div>

                                <p className="mt-4 text-muted-foreground text-sm">
                                    {summary}
                                </p>

                                <div className="mt-4 flex items-center gap-2">
                                    {role.is_locked ? (
                                        <p className="text-muted-foreground text-xs">
                                            This role always has full access and
                                            can't be changed.
                                        </p>
                                    ) : (
                                        <>
                                            {canEdit ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        dialog.openEdit(role)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                    Edit
                                                </Button>
                                            ) : null}
                                            {canDelete ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    disabled={inUse}
                                                    title={
                                                        inUse
                                                            ? "Reassign this role's people before deleting it."
                                                            : undefined
                                                    }
                                                    onClick={() =>
                                                        del.request(role)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    Delete
                                                </Button>
                                            ) : null}
                                        </>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <RoleEditorSheet
                key={dialog.editing ? dialog.editing.id : 'new'}
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                permissionGroups={permissionGroups}
                totalPermissions={totalPermissions}
                baseUrl={base}
            />

            <ConfirmDeleteDialog
                item={del.deleting}
                onOpenChange={(next) => {
                    if (!next) {
                        del.cancel();
                    }
                }}
                onConfirm={del.confirm}
                title="Delete this role?"
                description={
                    <>
                        “{del.deleting?.name}” will be removed. People aren't
                        deleted, but any left without a role will need a new
                        one.
                    </>
                }
            />
        </TenantLayout>
    );
}
