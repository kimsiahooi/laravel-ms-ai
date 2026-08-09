import { fireEvent, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage, renderPageWithForm } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SuppliersIndex } = await import('@/pages/tenant/suppliers');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        suppliers: paginator([
            {
                id: 1,
                name: 'Acme Metals',
                email: 'sales@acme.test',
                phone: '+60 3-1234 5678',
                address: null,
                notes: null,
            },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('suppliers index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<SuppliersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Suppliers' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Acme Metals')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no suppliers', () => {
        renderPage(<SuppliersIndex />, props({ suppliers: paginator([]) }));

        expect(screen.getByText(/no suppliers yet/i)).toBeInTheDocument();
    });

    it('hides create and row actions for a view-only user', () => {
        renderPage(
            <SuppliersIndex />,
            props({
                auth: { permissions: ['suppliers.view'], is_admin: false },
            }),
        );

        // The list still renders…
        expect(screen.getByText('Acme Metals')).toBeInTheDocument();
        // …but the create button is gone,
        expect(
            screen.queryByRole('button', { name: /new supplier/i }),
        ).not.toBeInTheDocument();
        // …and the row-actions menu is omitted entirely (no edit or delete).
        expect(
            screen.queryByRole('button', { name: /actions for/i }),
        ).not.toBeInTheDocument();
    });

    it('shows a rejected field on the create form', async () => {
        renderPageWithForm(<SuppliersIndex />, props({}), {
            errors: { email: 'The email has already been taken.' },
        });

        fireEvent.click(screen.getByRole('button', { name: /^new /i }));

        expect(
            await screen.findByText('The email has already been taken.'),
        ).toBeInTheDocument();
        expect(document.querySelector('[aria-invalid="true"]')).not.toBeNull();
    });
});
