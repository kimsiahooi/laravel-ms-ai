import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: StockTransfersIndex } = await import(
    '@/pages/tenant/stock-transfers'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        transfers: paginator([
            {
                id: 1,
                item: 'Steel sheet 1mm',
                from: 'Main Store',
                to: 'Penang Store',
                quantity: 5,
                user: 'Ada Lovelace',
                created_at: '2026-07-01T00:00:00+08:00',
            },
        ]),
        warehouses: [],
        items: [],
        filters: filters(),
        ...overrides,
    };
}

describe('stock transfers index', () => {
    it('renders the ledger with a row and the export control', () => {
        renderPage(<StockTransfersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Stock transfers' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no transfers', () => {
        renderPage(
            <StockTransfersIndex />,
            props({
                transfers: paginator([]),
                warehouses: [
                    { id: 1, name: 'Main Store' },
                    { id: 2, name: 'Penang Store' },
                ],
                items: [{ value: 'product:1', label: 'Widget' }],
            }),
        );

        expect(screen.getByText(/no stock transfers yet/i)).toBeInTheDocument();
    });

    it('guides you to add prerequisites and disables New when none exist', () => {
        renderPage(
            <StockTransfersIndex />,
            props({ transfers: paginator([]), warehouses: [], items: [] }),
        );

        expect(
            screen.getByText(/before you can create a stock transfer/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /add two warehouses/i }),
        ).toBeInTheDocument();
    });
});
