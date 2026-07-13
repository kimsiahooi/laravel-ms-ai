import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: CustomersIndex } = await import('@/pages/tenant/customers');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        customers: paginator([
            {
                id: 1,
                name: 'Globex Retail',
                email: 'hello@globex.test',
                phone: '+65 6123 4567',
                address: null,
                notes: null,
            },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('customers index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<CustomersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Customers' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Globex Retail')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no customers', () => {
        renderPage(<CustomersIndex />, props({ customers: paginator([]) }));

        expect(screen.getByText(/no customers yet/i)).toBeInTheDocument();
    });
});
