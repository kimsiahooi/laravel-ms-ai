import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: RawMaterialsIndex } = await import(
    '@/pages/tenant/raw-materials'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        rawMaterials: paginator([
            { id: 1, name: 'Steel sheet 1mm', sku: 'RM-STEEL-1MM', unit: 'kg' },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('raw materials index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<RawMaterialsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Raw materials' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Steel sheet 1mm')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no raw materials', () => {
        renderPage(
            <RawMaterialsIndex />,
            props({ rawMaterials: paginator([]) }),
        );

        expect(screen.getByText(/no raw materials yet/i)).toBeInTheDocument();
    });
});
