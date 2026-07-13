import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: StockTakeShow } = await import(
    '@/pages/tenant/stock-takes/show'
);

it('renders a posted stock take with its warehouse and counted items', () => {
    renderPage(<StockTakeShow />, {
        ...tenantProps(),
        take: {
            id: 2,
            warehouse: 'Main Store',
            status: 'posted',
            status_label: 'Posted',
            item_count: 1,
            total_variance: -3,
            notes: null,
            counted_at: '2026-07-02T00:00:00+08:00',
            created_at: '2026-07-01T00:00:00+08:00',
            items: [
                {
                    id: 1,
                    name: 'Desk fan 12-inch',
                    sku: 'FAN-12',
                    unit: 'pcs',
                    system_qty: 10,
                    counted_qty: 7,
                    variance: -3,
                },
            ],
        },
    });

    expect(screen.getByText('Main Store')).toBeInTheDocument();
    expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
});
