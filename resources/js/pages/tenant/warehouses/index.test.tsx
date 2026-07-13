import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: WarehousesIndex } = await import('@/pages/tenant/warehouses');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        warehouses: paginator([
            {
                id: 1,
                location_id: 1,
                location: 'KL HQ',
                name: 'Main Store',
                code: 'WH-KL',
                address: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items_in_stock: 12,
                low_stock: 2,
                out_of_stock: 1,
            },
        ]),
        locations: [],
        filters: filters(),
        ...overrides,
    };
}

describe('warehouses index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<WarehousesIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Warehouses' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Main Store')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no warehouses', () => {
        renderPage(<WarehousesIndex />, props({ warehouses: paginator([]) }));

        expect(screen.getByText(/no warehouses yet/i)).toBeInTheDocument();
    });
});
