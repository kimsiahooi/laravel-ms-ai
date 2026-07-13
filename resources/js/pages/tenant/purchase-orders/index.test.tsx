import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: PurchaseOrdersIndex } = await import(
    '@/pages/tenant/purchase-orders'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        orders: paginator([
            {
                id: 1,
                supplier: 'Acme Metals',
                status: 'pending',
                status_label: 'Pending',
                currency: 'MYR',
                item_count: 2,
                total: 250,
                received_at: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items: [],
            },
        ]),
        suppliers: [],
        rawMaterials: [],
        warehouses: [],
        filters: filters(),
        ...overrides,
    };
}

describe('purchase orders index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<PurchaseOrdersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Purchase orders' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Acme Metals')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no orders', () => {
        renderPage(<PurchaseOrdersIndex />, props({ orders: paginator([]) }));

        expect(screen.getByText(/no purchase orders yet/i)).toBeInTheDocument();
    });
});
