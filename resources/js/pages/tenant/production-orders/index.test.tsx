import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: ProductionOrdersIndex } = await import(
    '@/pages/tenant/production-orders'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        orders: paginator([
            {
                id: 1,
                product: 'Desk fan 12-inch',
                quantity: 5,
                status: 'pending',
                status_label: 'Pending',
                item_count: 3,
                completed_at: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items: [],
            },
        ]),
        products: [],
        productBoms: {},
        warehouses: [],
        filters: filters(),
        ...overrides,
    };
}

describe('production orders index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<ProductionOrdersIndex />, props());

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Production orders',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no orders', () => {
        renderPage(<ProductionOrdersIndex />, props({ orders: paginator([]) }));

        expect(
            screen.getByText(/no production orders yet/i),
        ).toBeInTheDocument();
    });
});
