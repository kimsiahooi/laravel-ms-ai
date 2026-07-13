import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: CategoriesIndex } = await import('@/pages/tenant/categories');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        categories: paginator([
            { id: 1, name: 'Electronics', description: 'Electronic parts' },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('categories index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<CategoriesIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Categories' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Electronics')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no categories', () => {
        renderPage(<CategoriesIndex />, props({ categories: paginator([]) }));

        expect(screen.getByText(/no categories yet/i)).toBeInTheDocument();
    });
});
