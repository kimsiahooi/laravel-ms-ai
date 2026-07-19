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

const { default: ProductionOrderShow } = await import(
    '@/pages/tenant/production-orders/show'
);

function order(overrides: Record<string, unknown> = {}) {
    return {
        id: 7,
        product: 'Desk fan 12-inch',
        status: 'completed',
        status_label: 'Completed',
        quantity: 5,
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

describe('production order show', () => {
    it('renders the interactive detail with product, status and line items', () => {
        renderPage(<ProductionOrderShow />, props());

        expect(
            screen.getByRole('heading', { name: 'Production order #7' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        // A completed build can't be completed again — only the Print action shows.
        expect(
            screen.getByRole('link', { name: /print/i }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /complete/i }),
        ).not.toBeInTheDocument();
    });

    it('offers Complete and Cancel actions while pending', () => {
        renderPage(
            <ProductionOrderShow />,
            props({
                order: order({
                    status: 'pending',
                    status_label: 'Pending',
                    completed_at: null,
                }),
            }),
        );

        expect(
            screen.getByRole('button', { name: /complete build/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /cancel/i }),
        ).toBeInTheDocument();
    });

    it('renders the printable document in print mode', () => {
        renderPage(<ProductionOrderShow />, props({ print: true }));

        expect(screen.getByText('Production Order')).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
    });
});
