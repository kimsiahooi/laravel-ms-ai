import { usePage } from '@inertiajs/react';

/**
 * Reads the signed-in tenant user's permissions from shared props. `can(name)`
 * mirrors the server-side gate (`AuthorizeTenantRoute`) so the UI can hide what a
 * person can't do — enforcement still lives on the server, so this is convenience,
 * never the security boundary. `isAdmin` is true for the built-in Administrator.
 */
export function usePermissions(): {
    can: (permission: string) => boolean;
    isAdmin: boolean;
} {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];

    return {
        can: (permission: string) => permissions.includes(permission),
        isAdmin: auth?.is_admin ?? false,
    };
}
