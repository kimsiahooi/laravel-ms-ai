import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

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
});
