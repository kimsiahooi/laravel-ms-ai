import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: Reports } = await import('@/pages/tenant/reports/index');

function props(overrides: Record<string, unknown> = {}) {
    return {
        tenant: { slug: 'acme', name: 'Acme' },
        filters: {
            from: '2026-07-01T00:00:00+08:00',
            to: '2026-07-12T23:59:59+08:00',
        },
        sales: { count: 2, quantity: 10, amount: 250 },
        purchases: { count: 1, quantity: 5, amount: 100 },
        production: { count: 0, quantity: 0 },
        movements: [
            {
                reason: 'adjustment',
                label: 'Adjustment',
                count: 3,
                net_quantity: -5,
            },
        ],
        lowStock: [
            {
                warehouse: 'KL · Main',
                item: 'Steel',
                unit: 'kg',
                on_hand: 8,
                reorder_level: 20,
            },
        ],
        ...overrides,
    };
}

describe('tenant reports', () => {
    it('renders the summary tiles and both breakdown tables with data', () => {
        renderPage(<Reports />, props());

        expect(screen.getByText('Reports')).toBeInTheDocument();
        expect(screen.getByText('Adjustment')).toBeInTheDocument(); // movement row
        expect(screen.getByText('Steel')).toBeInTheDocument(); // low-stock row
    });

    it('shows an all-clear empty state when nothing is low on stock', () => {
        renderPage(<Reports />, props({ movements: [], lowStock: [] }));

        expect(
            screen.getByText('No stock movements in this period.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText("Everything's above its reorder level"),
        ).toBeInTheDocument();
    });
});
