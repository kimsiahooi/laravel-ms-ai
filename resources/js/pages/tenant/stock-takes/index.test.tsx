import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: StockTakesIndex } = await import('@/pages/tenant/stock-takes');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        takes: paginator([
            {
                id: 1,
                warehouse: 'Main Store',
                status: 'draft',
                status_label: 'Draft',
                item_count: 5,
                total_variance: -3,
                notes: null,
                counted_at: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items: [],
            },
        ]),
        warehouses: [],
        filters: filters(),
        ...overrides,
    };
}

describe('stock takes index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<StockTakesIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Stock takes' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Main Store')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no counts', () => {
        renderPage(<StockTakesIndex />, props({ takes: paginator([]) }));

        expect(screen.getByText(/no stock takes yet/i)).toBeInTheDocument();
    });
});
