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

const { default: SalesReturnShow } = await import(
    '@/pages/tenant/sales-returns/show'
);

function returnRecord(overrides: Record<string, unknown> = {}) {
    return {
        id: 4,
        customer: 'Nadia Rahman',
        customer_id: 3,
        status: 'completed',
        status_label: 'Completed',
        item_count: 1,
        total_quantity: 6,
        notes: null,
        completed_at: '2026-07-02T00:00:00+08:00',
        created_at: '2026-07-01T00:00:00+08:00',
        items: [
            {
                id: 1,
                product_id: 1,
                name: 'Ceramic mug',
                quantity: 6,
            },
        ],
        ...overrides,
    };
}

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        return: returnRecord(),
        warehouses: [{ id: 1, name: 'Main Store' }],
        print: false,
        ...overrides,
    };
}

describe('sales return show', () => {
    it('renders the interactive detail with customer, status and line items', () => {
        renderPage(<SalesReturnShow />, props());

        expect(
            screen.getByRole('heading', { name: 'Sales return #4' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Ceramic mug')).toBeInTheDocument();
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
            <SalesReturnShow />,
            props({
                return: returnRecord({
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
        renderPage(<SalesReturnShow />, props({ print: true }));

        expect(screen.getByText('Sales Return')).toBeInTheDocument();
        expect(screen.getByText('Ceramic mug')).toBeInTheDocument();
    });
});
