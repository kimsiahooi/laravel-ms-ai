import { fireEvent, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { expect, it, vi } from 'vitest';
import { paginator, tenantProps } from '@/test/fixtures';
import { renderPage, renderPageWithForm } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

vi.mock('sonner', async (importOriginal) => ({
    ...(await importOriginal<typeof import('sonner')>()),
    toast: { error: vi.fn(), success: vi.fn() },
}));

const { default: WarehouseShow } = await import(
    '@/pages/tenant/warehouses/show'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        warehouse: {
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
        items: paginator([
            {
                stockable_type: 'product',
                stockable_id: 1,
                item: 'Desk fan 12-inch',
                sku: 'FAN-12',
                type: 'Product',
                unit: 'pcs',
                on_hand: 8,
                min_stock: 5,
                needs_reorder: false,
            },
        ]),
        summary: { in_stock: 1, needs_reorder: 0 },
        filters: { search: '', per_page: 10, view: '' },
        ...overrides,
    };
}

it('puts the reorder level back and says so when saving it fails', () => {
    renderPageWithForm(<WarehouseShow />, props(), { onSubmit: 'error' });

    const input = screen.getByLabelText('Reorder level for Desk fan 12-inch');
    fireEvent.change(input, { target: { value: '99' } });
    fireEvent.blur(input);

    // The typed value is rolled back rather than left looking saved…
    expect(input).toHaveValue(5);
    // …and the failure is actually reported, not swallowed.
    expect(toast.error).toHaveBeenCalledWith(
        'Could not update the reorder level.',
    );
});

it('renders a warehouse with its stock summary and item rows', () => {
    renderPage(<WarehouseShow />, {
        ...tenantProps(),
        warehouse: {
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
        items: paginator([
            {
                stockable_type: 'product',
                stockable_id: 1,
                item: 'Desk fan 12-inch',
                sku: 'FAN-12',
                type: 'Product',
                unit: 'pcs',
                on_hand: 8,
                min_stock: 5,
                needs_reorder: false,
            },
        ]),
        summary: { in_stock: 1, needs_reorder: 0 },
        filters: { search: '', per_page: 10, view: '' },
    });

    expect(screen.getByText('Main Store')).toBeInTheDocument();
    expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
});
