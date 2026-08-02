import { fireEvent, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: RolesIndex } = await import('@/pages/tenant/roles');

const permissionGroups = [
    {
        key: 'suppliers',
        label: 'Suppliers',
        permissions: [
            { name: 'suppliers.view', action: 'view', label: 'View suppliers' },
            {
                name: 'suppliers.create',
                action: 'create',
                label: 'Create suppliers',
            },
            {
                name: 'suppliers.update',
                action: 'update',
                label: 'Edit suppliers',
            },
            {
                name: 'suppliers.delete',
                action: 'delete',
                label: 'Delete suppliers',
            },
        ],
    },
    {
        key: 'reports',
        label: 'Reports',
        permissions: [
            { name: 'reports.view', action: 'view', label: 'View reports' },
        ],
    },
];

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps({
            auth: {
                user: { name: 'Ada Lovelace', email: 'ada@acme.test' },
                permissions: [
                    'roles.view',
                    'roles.create',
                    'roles.update',
                    'roles.delete',
                ],
                is_admin: true,
            },
        }),
        roles: [
            {
                id: 1,
                name: 'Administrator',
                permissions: [
                    'suppliers.view',
                    'suppliers.create',
                    'suppliers.update',
                    'suppliers.delete',
                    'reports.view',
                ],
                is_locked: true,
                user_count: 1,
            },
            {
                id: 2,
                name: 'Warehouse staff',
                permissions: ['suppliers.view'],
                is_locked: false,
                user_count: 0,
            },
        ],
        permissionGroups,
        ...overrides,
    };
}

describe('roles index', () => {
    it('lists roles with their access summary', () => {
        renderPage(<RolesIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Roles' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Administrator')).toBeInTheDocument();
        expect(screen.getByText('Built-in')).toBeInTheDocument();
        expect(screen.getByText('Full access')).toBeInTheDocument();
        expect(screen.getByText('Warehouse staff')).toBeInTheDocument();
        expect(screen.getByText('1 of 5 permissions')).toBeInTheDocument();
    });

    it('shows the empty state when there are no roles', () => {
        renderPage(<RolesIndex />, props({ roles: [] }));

        expect(screen.getByText(/no roles yet/i)).toBeInTheDocument();
    });

    it('opens the permission matrix editor', async () => {
        renderPage(<RolesIndex />, props());

        fireEvent.click(screen.getByRole('button', { name: /new role/i }));

        expect(await screen.findByText('Role name')).toBeInTheDocument();
        expect(screen.getByText('Permissions')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /select all/i }),
        ).toBeInTheDocument();
        // A per-permission checkbox from the catalog is present.
        expect(
            screen.getByRole('checkbox', { name: 'View suppliers' }),
        ).toBeInTheDocument();
    });
});
