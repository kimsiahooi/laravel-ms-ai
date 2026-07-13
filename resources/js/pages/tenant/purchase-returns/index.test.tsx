import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: PurchaseReturnsIndex } = await import(
    '@/pages/tenant/purchase-returns'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        returns: paginator([
            {
                id: 1,
                supplier: 'Acme Metals',
                status: 'pending',
                status_label: 'Pending',
                item_count: 1,
                total_quantity: 5,
                completed_at: null,
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

describe('purchase returns index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<PurchaseReturnsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Purchase returns' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Acme Metals')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no returns', () => {
        renderPage(<PurchaseReturnsIndex />, props({ returns: paginator([]) }));

        expect(
            screen.getByText(/no purchase returns yet/i),
        ).toBeInTheDocument();
    });
});
