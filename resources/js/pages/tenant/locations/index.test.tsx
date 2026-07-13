import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: LocationsIndex } = await import('@/pages/tenant/locations');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        locations: paginator([
            {
                id: 1,
                name: 'KL HQ',
                code: 'KL-HQ',
                address: 'Jalan Ampang, Kuala Lumpur',
                warehouse_count: 2,
            },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('locations index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<LocationsIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Locations' }),
        ).toBeInTheDocument();
        expect(screen.getByText('KL HQ')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no locations', () => {
        renderPage(<LocationsIndex />, props({ locations: paginator([]) }));

        expect(screen.getByText(/no locations yet/i)).toBeInTheDocument();
    });
});
