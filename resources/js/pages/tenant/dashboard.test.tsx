import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { renderPage } from '@/test/render';

// Focus the render on the dashboard body, not the sidebar shell.
vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

// Imported after the mock is declared (vi.mock is hoisted regardless).
const { default: Dashboard } = await import('@/pages/tenant/dashboard');

function props(overrides: Record<string, unknown> = {}) {
    return {
        auth: { user: { name: 'Ada Lovelace', email: 'ada@acme.test' } },
        tenant: { slug: 'acme', name: 'Acme' },
        organization: { name: 'Acme', slug: 'acme', logo: null },
        filters: {
            from: '2026-07-06T00:00:00+08:00',
            to: '2026-07-12T23:59:59+08:00',
        },
        kpis: {
            sales: { count: 1, amount: 1250 },
            purchases: { count: 0, amount: 0 },
            production: { count: 0, quantity: 0 },
            low_stock: 2,
        },
        series: [
            { day: '2026-07-06', label: 'Jul 6', sales: 0, purchases: 0 },
            { day: '2026-07-07', label: 'Jul 7', sales: 1250, purchases: 0 },
        ],
        movements: [
            { reason: 'sales_fulfillment', label: 'Sales fulfilment', net: -5 },
        ],
        // Fully onboarded by default, so the checklist stays hidden and the KPI
        // assertions aren't competing with setup nudges.
        onboarding: {
            location: true,
            warehouse: true,
            catalog: true,
            bom: true,
            stock: true,
            order: true,
        },
        ...overrides,
    };
}

describe('tenant dashboard', () => {
    it('renders the KPI tiles and both chart cards with data', () => {
        renderPage(<Dashboard />, props());

        expect(screen.getByText('Production')).toBeInTheDocument();
        expect(screen.getByText('Low / out of stock')).toBeInTheDocument();
        // formatQuantity(1250) with the pinned locale — proves the KPI + formatter path.
        expect(screen.getByText('1,250')).toBeInTheDocument();
        expect(screen.getByText('Sales vs Purchases')).toBeInTheDocument();
        expect(screen.getByText('Stock movements')).toBeInTheDocument();
    });

    it('shows friendly empty states for a range with no activity', () => {
        renderPage(
            <Dashboard />,
            props({
                kpis: {
                    sales: { count: 0, amount: 0 },
                    purchases: { count: 0, amount: 0 },
                    production: { count: 0, quantity: 0 },
                    low_stock: 0,
                },
                series: [],
                movements: [],
            }),
        );

        expect(
            screen.getByText('No sales or purchases in this range yet.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('No stock movements in this range.'),
        ).toBeInTheDocument();
    });

    it('shows the onboarding checklist until setup is complete', () => {
        renderPage(
            <Dashboard />,
            props({
                onboarding: {
                    location: true,
                    warehouse: false,
                    catalog: false,
                    bom: false,
                    stock: false,
                    order: false,
                },
            }),
        );

        expect(
            screen.getByText('Get your workspace ready'),
        ).toBeInTheDocument();
        expect(screen.getByText(/1 of 6 done/)).toBeInTheDocument();
    });
});
