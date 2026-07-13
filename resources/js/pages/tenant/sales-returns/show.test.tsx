import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/print-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SalesReturnShow } = await import(
    '@/pages/tenant/sales-returns/show'
);

it('renders a sales return with its customer and line items', () => {
    renderPage(<SalesReturnShow />, {
        ...tenantProps(),
        return: {
            id: 6,
            customer: 'Globex Retail',
            status: 'completed',
            status_label: 'Completed',
            item_count: 1,
            total_quantity: 4,
            completed_at: '2026-07-02T00:00:00+08:00',
            created_at: '2026-07-01T00:00:00+08:00',
            items: [
                {
                    id: 1,
                    product_id: 1,
                    name: 'Desk fan 12-inch',
                    quantity: 4,
                },
            ],
        },
    });

    expect(screen.getByText('Globex Retail')).toBeInTheDocument();
    expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
});
