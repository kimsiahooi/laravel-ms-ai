import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));
vi.mock('@/layouts/print-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: PurchaseOrderShow } = await import(
    '@/pages/tenant/purchase-orders/show'
);

function order(overrides: Record<string, unknown> = {}) {
    return {
        id: 7,
        supplier: 'Acme Metals',
        status: 'received',
        status_label: 'Received',
        currency: 'MYR',
        item_count: 1,
        total: 250,
        notes: null,
        received_at: '2026-07-02T00:00:00+08:00',
        created_at: '2026-07-01T00:00:00+08:00',
        items: [
            {
                id: 1,
                raw_material_id: 1,
                name: 'Steel sheet 1mm',
                quantity: 100,
                unit_cost: 2.5,
            },
        ],
        ...overrides,
    };
}

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        order: order(),
        warehouses: [{ id: 1, name: 'Main Store' }],
        print: false,
        ...overrides,
    };
}

describe('purchase order show', () => {
    it('renders the interactive detail with supplier, status and line items', () => {
        renderPage(<PurchaseOrderShow />, props());

        expect(
            screen.getByRole('heading', { name: 'Purchase order #7' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Received')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        // A received order can't be received again — only the Print action shows.
        expect(
            screen.getByRole('link', { name: /print/i }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /receive/i }),
        ).not.toBeInTheDocument();
    });

    it('offers Receive and Cancel actions while pending', () => {
        renderPage(
            <PurchaseOrderShow />,
            props({
                order: order({
                    status: 'pending',
                    status_label: 'Pending',
                    received_at: null,
                }),
            }),
        );

        expect(
            screen.getByRole('button', { name: /receive/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /cancel/i }),
        ).toBeInTheDocument();
    });

    it('renders the printable document in print mode', () => {
        renderPage(<PurchaseOrderShow />, props({ print: true }));

        expect(screen.getByText('Purchase Order')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
    });
});
