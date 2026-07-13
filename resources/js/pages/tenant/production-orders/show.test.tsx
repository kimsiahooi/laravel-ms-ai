import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/print-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: ProductionOrderShow } = await import(
    '@/pages/tenant/production-orders/show'
);

it('renders a production order with its product and BOM lines', () => {
    renderPage(<ProductionOrderShow />, {
        ...tenantProps(),
        order: {
            id: 4,
            product: 'Desk fan 12-inch',
            quantity: 5,
            status: 'completed',
            status_label: 'Completed',
            item_count: 1,
            completed_at: '2026-07-02T00:00:00+08:00',
            created_at: '2026-07-01T00:00:00+08:00',
            items: [
                {
                    id: 1,
                    raw_material_id: 1,
                    name: 'Steel sheet 1mm',
                    quantity_per_unit: 0.5,
                    quantity_required: 2.5,
                },
            ],
        },
    });

    expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
    expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
});
