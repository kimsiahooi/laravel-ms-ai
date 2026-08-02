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

const { default: SalesOrderShow } = await import(
    '@/pages/tenant/sales-orders/show'
);

function order(overrides: Record<string, unknown> = {}) {
    return {
        id: 7,
        customer: 'Globex Retail',
        customer_id: 1,
        buyer: null,
        status: 'fulfilled',
        status_label: 'Fulfilled',
        currency: 'MYR',
        exchange_rate: 1,
        base_currency: 'MYR',
        item_count: 1,
        subtotal: 250,
        tax_rate: 0,
        tax_amount: 0,
        total: 250,
        base_total: 250,
        notes: null,
        fulfilled_at: '2026-07-02T00:00:00+08:00',
        created_at: '2026-07-01T00:00:00+08:00',
        items: [
            {
                id: 1,
                product_id: 1,
                name: 'Widget Pro',
                quantity: 100,
                unit_price: 2.5,
                taxable: true,
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

describe('sales order show', () => {
    it('renders the interactive detail with customer, status and line items', () => {
        renderPage(<SalesOrderShow />, props());

        expect(
            screen.getByRole('heading', { name: 'Sales order #7' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Fulfilled')).toBeInTheDocument();
        expect(screen.getByText('Widget Pro')).toBeInTheDocument();
        // A fulfilled order can't be fulfilled again — only the Print action shows.
        expect(
            screen.getByRole('link', { name: /print/i }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /fulfill/i }),
        ).not.toBeInTheDocument();
    });

    it('offers Fulfill and Cancel actions while pending', () => {
        renderPage(
            <SalesOrderShow />,
            props({
                order: order({
                    status: 'pending',
                    status_label: 'Pending',
                    fulfilled_at: null,
                }),
            }),
        );

        expect(
            screen.getByRole('button', { name: /fulfill/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /cancel/i }),
        ).toBeInTheDocument();
    });

    it('renders the printable document in print mode', () => {
        renderPage(<SalesOrderShow />, props({ print: true }));

        expect(screen.getByText('Sales Order')).toBeInTheDocument();
        expect(screen.getByText('Widget Pro')).toBeInTheDocument();
    });

    it('breaks out subtotal + tax + total when the order is taxed (R15)', () => {
        renderPage(
            <SalesOrderShow />,
            props({
                order: order({
                    subtotal: 250,
                    tax_rate: 10,
                    tax_amount: 25,
                    total: 275,
                    base_total: 275,
                }),
            }),
        );

        expect(screen.getByText('Subtotal')).toBeInTheDocument();
        expect(screen.getByText('Tax (10%)')).toBeInTheDocument();
        // Subtotal 250 + 10% tax 25 = 275 grand total (unique amount in the footer).
        expect(screen.getByText(/275\.00/)).toBeInTheDocument();
    });

    it('shows only a single total when the order carries no tax (R15)', () => {
        renderPage(<SalesOrderShow />, props());

        expect(screen.queryByText('Subtotal')).not.toBeInTheDocument();
        expect(screen.queryByText(/Tax \(/)).not.toBeInTheDocument();
    });
});
