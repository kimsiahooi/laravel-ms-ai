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

const { default: PurchaseReturnShow } = await import(
    '@/pages/tenant/purchase-returns/show'
);

function purchaseReturn(overrides: Record<string, unknown> = {}) {
    return {
        id: 7,
        supplier: 'Acme Metals',
        supplier_id: 1,
        status: 'completed',
        status_label: 'Completed',
        item_count: 1,
        total_quantity: 100,
        notes: null,
        completed_at: '2026-07-02T00:00:00+08:00',
        created_at: '2026-07-01T00:00:00+08:00',
        items: [
            {
                id: 1,
                raw_material_id: 1,
                name: 'Steel sheet 1mm',
                quantity: 100,
            },
        ],
        ...overrides,
    };
}

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        return: purchaseReturn(),
        warehouses: [{ id: 1, name: 'Main Store' }],
        print: false,
        ...overrides,
    };
}

describe('purchase return show', () => {
    it('renders the interactive detail with supplier, status and line items', () => {
        renderPage(<PurchaseReturnShow />, props());

        expect(
            screen.getByRole('heading', { name: 'Purchase return #7' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        // A completed return can't be completed again — only the Print action shows.
        expect(
            screen.getByRole('link', { name: /print/i }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /complete return/i }),
        ).not.toBeInTheDocument();
    });

    it('offers Complete and Cancel actions while pending', () => {
        renderPage(
            <PurchaseReturnShow />,
            props({
                return: purchaseReturn({
                    status: 'pending',
                    status_label: 'Pending',
                    completed_at: null,
                }),
            }),
        );

        expect(
            screen.getByRole('button', { name: /complete return/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /cancel/i }),
        ).toBeInTheDocument();
    });

    it('renders the printable document in print mode', () => {
        renderPage(<PurchaseReturnShow />, props({ print: true }));

        expect(screen.getByText('Purchase Return')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
    });
});
