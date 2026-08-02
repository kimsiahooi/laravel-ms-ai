import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: UsersIndex } = await import('@/pages/tenant/users');

function props(overrides: Record<string, unknown> = {}) {
    const { auth: authOverride, ...rest } = overrides as {
        auth?: Record<string, unknown>;
    } & Record<string, unknown>;

    return {
        ...tenantProps({
            auth: {
                user: { name: 'Ada Lovelace', email: 'ada@acme.test' },
                permissions: [
                    'users.view',
                    'users.create',
                    'users.update',
                    'users.delete',
                ],
                is_admin: true,
                ...authOverride,
            },
        }),
        users: paginator([
            {
                id: 1,
                name: 'Ada Lovelace',
                email: 'ada@acme.test',
                role: 'Administrator',
                created_at: '2026-01-01T00:00:00Z',
                is_active: true,
                is_self: true,
            },
            {
                id: 2,
                name: 'Mel Chen',
                email: 'mel@acme.test',
                role: 'Warehouse staff',
                created_at: '2026-02-01T00:00:00Z',
                is_active: false,
                is_self: false,
            },
        ]),
        roles: ['Administrator', 'Warehouse staff'],
        filters: filters(),
        ...rest,
    };
}

describe('users index', () => {
    it('lists people with their role and status', () => {
        renderPage(<UsersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Users' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('You')).toBeInTheDocument();
        expect(screen.getByText('Mel Chen')).toBeInTheDocument();
        expect(screen.getByText('Deactivated')).toBeInTheDocument();
    });

    it('shows the New user button to an admin who can create', () => {
        renderPage(<UsersIndex />, props());

        expect(
            screen.getByRole('button', { name: /new user/i }),
        ).toBeInTheDocument();
    });

    it('hides the New user button without create permission', () => {
        renderPage(
            <UsersIndex />,
            props({ auth: { permissions: ['users.view'], is_admin: false } }),
        );

        expect(
            screen.queryByRole('button', { name: /new user/i }),
        ).not.toBeInTheDocument();
    });

    it('shows the empty state when there are no users', () => {
        renderPage(<UsersIndex />, props({ users: paginator([]) }));

        expect(screen.getByText(/it's just you so far/i)).toBeInTheDocument();
    });
});
