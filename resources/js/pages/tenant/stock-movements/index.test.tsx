import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: StockMovementsIndex } = await import(
    '@/pages/tenant/stock-movements'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        movements: paginator([
            {
                id: 1,
                warehouse: 'Main Store',
                item: 'Steel sheet 1mm',
                quantity: 10,
                reason: 'adjustment',
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

describe('stock movements index', () => {
    it('renders the ledger with a row and the export control', () => {
        renderPage(<StockMovementsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Stock movements' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no movements', () => {
        renderPage(
            <StockMovementsIndex />,
            props({ movements: paginator([]) }),
        );

        expect(screen.getByText(/no stock movements yet/i)).toBeInTheDocument();
    });
});
