import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: ActivityIndex } = await import('@/pages/tenant/activity');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        activities: paginator([
            {
                id: 1,
                event: 'created',
                subject_type: 'product',
                subject: 'Desk fan 12-inch',
                causer: 'Ada Lovelace',
                changes: [],
                created_at: '2026-07-01T00:00:00+08:00',
            },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('activity index', () => {
    it('renders the activity log with a row and the export control', () => {
        renderPage(<ActivityIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Activity' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/desk fan 12-inch/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there is no activity', () => {
        renderPage(<ActivityIndex />, props({ activities: paginator([]) }));

        expect(screen.getByText(/no activity yet/i)).toBeInTheDocument();
    });
});
