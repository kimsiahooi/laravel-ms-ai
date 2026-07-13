import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/print-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SalesOrderShow } = await import(
    '@/pages/tenant/sales-orders/show'
);

it('renders a sales order with its customer and line items', () => {
    renderPage(<SalesOrderShow />, {
        ...tenantProps(),
        order: {
            id: 9,
            customer: 'Globex Retail',
            status: 'fulfilled',
            status_label: 'Fulfilled',
            currency: 'MYR',
            item_count: 1,
            total: 20,
            fulfilled_at: '2026-07-02T00:00:00+08:00',
            created_at: '2026-07-01T00:00:00+08:00',
            items: [
                {
                    id: 1,
                    product_id: 1,
                    name: 'Desk fan 12-inch',
                    quantity: 2,
                    unit_price: 10,
                },
            ],
        },
    });

    expect(screen.getByText('Globex Retail')).toBeInTheDocument();
    expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
});
