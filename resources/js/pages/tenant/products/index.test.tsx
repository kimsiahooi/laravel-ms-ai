import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: ProductsIndex } = await import('@/pages/tenant/products');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        products: paginator([
            {
                id: 1,
                name: 'Desk fan 12-inch',
                sku: 'FAN-12',
                barcode: null,
                description: null,
                image_url: null,
                category_id: 1,
                supplier_id: 1,
                category: 'Electronics',
                supplier: 'Acme Metals',
                unit: 'pcs',
                created_at: '2026-07-01T00:00:00+08:00',
                bom: [],
            },
        ]),
        categories: [],
        suppliers: [],
        rawMaterials: [],
        filters: filters(),
        ...overrides,
    };
}

describe('products index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<ProductsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Products' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Desk fan 12-inch')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no products', () => {
        renderPage(<ProductsIndex />, props({ products: paginator([]) }));

        expect(screen.getByText(/no products yet/i)).toBeInTheDocument();
    });
});
