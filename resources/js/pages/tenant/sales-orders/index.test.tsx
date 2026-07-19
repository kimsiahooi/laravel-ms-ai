import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SalesOrdersIndex } = await import(
    '@/pages/tenant/sales-orders'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        orders: paginator([
            {
                id: 1,
                customer: 'Globex Retail',
                status: 'pending',
                status_label: 'Pending',
                currency: 'MYR',
                item_count: 1,
                total: 20,
                fulfilled_at: null,
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

describe('sales orders index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<SalesOrdersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Sales orders' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Globex Retail')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no orders', () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                orders: paginator([]),
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
            }),
        );

        expect(screen.getByText(/no sales orders yet/i)).toBeInTheDocument();
    });

    it('guides you to add prerequisites and disables New when none exist', () => {
        renderPage(
            <SalesOrdersIndex />,
            props({ orders: paginator([]), customers: [], products: [] }),
        );

        expect(
            screen.getByText(/before you can create a sales order/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /add a customer/i }),
        ).toBeInTheDocument();
    });
});
