import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/print-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: PurchaseReturnShow } = await import(
    '@/pages/tenant/purchase-returns/show'
);

it('renders a purchase return with its supplier and line items', () => {
    renderPage(<PurchaseReturnShow />, {
        ...tenantProps(),
        return: {
            id: 3,
            supplier: 'Acme Metals',
            status: 'completed',
            status_label: 'Completed',
            item_count: 1,
            total_quantity: 5,
            completed_at: '2026-07-02T00:00:00+08:00',
            created_at: '2026-07-01T00:00:00+08:00',
            items: [
                {
                    id: 1,
                    raw_material_id: 1,
                    name: 'Steel sheet 1mm',
                    quantity: 5,
                },
            ],
        },
    });

    expect(screen.getByText('Acme Metals')).toBeInTheDocument();
    expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
});
