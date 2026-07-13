import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SalesReturnsIndex } = await import(
    '@/pages/tenant/sales-returns'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        returns: paginator([
            {
                id: 1,
                customer: 'Globex Retail',
                status: 'pending',
                status_label: 'Pending',
                item_count: 1,
                total_quantity: 4,
                completed_at: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items: [],
            },
        ]),
        customers: [],
        products: [],
        warehouses: [],
        filters: filters(),
        ...overrides,
    };
}

describe('sales returns index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<SalesReturnsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Sales returns' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Globex Retail')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no returns', () => {
        renderPage(<SalesReturnsIndex />, props({ returns: paginator([]) }));

        expect(screen.getByText(/no sales returns yet/i)).toBeInTheDocument();
    });
});
