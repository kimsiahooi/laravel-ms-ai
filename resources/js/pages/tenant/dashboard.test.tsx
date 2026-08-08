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

function kpis(overrides: Record<string, unknown> = {}) {
    return {
        currency: 'USD',
        sales: { count: 1, amount: 1250 },
        purchases: { count: 0, amount: 0 },
        production: { count: 3, quantity: 884 },
        ...overrides,
    };
}

function snapshot(overrides: Record<string, unknown> = {}) {
    return {
        stock_value: { amount: 24100, valued: 12, unvalued: 0 },
        incoming: { count: 5, amount: 84200, overdue: 1 },
        production: { active: 4, blocked: 1 },
        low_stock: 6,
        out_of_stock: 2,
        ready_sales: 3,
        ...overrides,
    };
}

/** Nothing outstanding anywhere — the all-clear state. */
function quietSnapshot(overrides: Record<string, unknown> = {}) {
    return snapshot({
        incoming: { count: 0, amount: 0, overdue: 0 },
        production: { active: 0, blocked: 0 },
        low_stock: 0,
        out_of_stock: 0,
        ready_sales: 0,
        ...overrides,
    });
}

function props(overrides: Record<string, unknown> = {}) {
    return {
        auth: { user: { name: 'Ada Lovelace', email: 'ada@acme.test' } },
        tenant: { slug: 'acme', name: 'Acme' },
        organization: { name: 'Acme', slug: 'acme', logo: null },
        filters: {
            from: '2026-07-06T00:00:00+08:00',
            to: '2026-07-12T23:59:59+08:00',
        },
        kpis: kpis(),
        snapshot: snapshot(),
        series: [
            { day: '2026-07-06', label: 'Jul 6', sales: 0, purchases: 0 },
            { day: '2026-07-07', label: 'Jul 7', sales: 1250, purchases: 0 },
        ],
        movements: [
            {
                reason: 'purchase_receipt',
                label: 'Purchase receipt',
                net: 9400,
            },
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
    it('leads with stock value, stock alerts, incoming and production (R17)', () => {
        renderPage(<Dashboard />, props());

        expect(screen.getByText('Stock value')).toBeInTheDocument();
        expect(screen.getByText('$24,100.00')).toBeInTheDocument();
        expect(screen.getByText('Low / out of stock')).toBeInTheDocument();
        expect(
            screen.getByText('2 out of stock · reorder now'),
        ).toBeInTheDocument();
        expect(screen.getByText('Incoming')).toBeInTheDocument();
        expect(
            screen.getByText('5 orders on the way · 1 late'),
        ).toBeInTheDocument();
        expect(screen.getByText('4 active')).toBeInTheDocument();
        expect(screen.getByText('Sales vs Purchases')).toBeInTheDocument();
        expect(screen.getByText('Stock movements')).toBeInTheDocument();
    });

    it('links every KPI card through to a real page (R17)', () => {
        renderPage(<Dashboard />, props());

        const hrefOf = (label: string) =>
            screen.getByText(label).closest('a')?.getAttribute('href');

        // Every target must be a page a browser can actually open — not one of the
        // JSON lookup endpoints (/stock/on-hand) the dialogs use.
        expect(hrefOf('Stock value')).toBe('/acme/warehouses');
        expect(hrefOf('Low / out of stock')).toBe('/acme/reports');
        expect(hrefOf('Incoming')).toBe('/acme/purchase-orders');
        expect(hrefOf('Production')).toBe('/acme/production-orders');
    });

    it('never announces "0 out of stock" when things are merely low (R17)', () => {
        renderPage(
            <Dashboard />,
            props({
                snapshot: snapshot({ low_stock: 3, out_of_stock: 0 }),
            }),
        );

        expect(screen.queryByText(/0 out of stock/)).not.toBeInTheDocument();
        expect(
            screen.getByText('3 below the reorder level'),
        ).toBeInTheDocument();
        expect(screen.getByText('3 items are running low')).toBeInTheDocument();
    });

    it('keeps the completed-production units R16 asked for', () => {
        renderPage(
            <Dashboard />,
            props({
                snapshot: snapshot({ production: { active: 2, blocked: 0 } }),
            }),
        );

        expect(screen.getByText('3 completed · 884 units')).toBeInTheDocument();
    });

    it('lists what needs attention, each with a way to deal with it (R17)', () => {
        renderPage(<Dashboard />, props());

        expect(screen.getByText('Needs your attention')).toBeInTheDocument();
        expect(screen.getByText('2 items have run out')).toBeInTheDocument();
        expect(
            screen.getByText('1 build cannot start — not enough materials'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('1 purchase order is past the delivery date'),
        ).toBeInTheDocument();
        // 6 low − 2 out = 4 still running low, not double-counted as "run out".
        expect(screen.getByText('4 items are running low')).toBeInTheDocument();
        expect(
            screen.getByText('3 sales orders are ready to ship'),
        ).toBeInTheDocument();

        expect(screen.getByRole('link', { name: 'Fulfil' })).toHaveAttribute(
            'href',
            '/acme/sales-orders',
        );
    });

    it('says so plainly when nothing is waiting (R17)', () => {
        renderPage(<Dashboard />, props({ snapshot: quietSnapshot() }));

        expect(screen.getByText("You're all caught up")).toBeInTheDocument();
    });

    it('does not claim you are caught up before setup is done (R17)', () => {
        renderPage(
            <Dashboard />,
            props({
                snapshot: quietSnapshot(),
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

        // The setup checklist is on screen, so "all caught up" would contradict it.
        expect(
            screen.queryByText("You're all caught up"),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
    });

    it('admits which stock it could not put a price on (R17)', () => {
        renderPage(
            <Dashboard />,
            props({
                snapshot: snapshot({
                    stock_value: { amount: 100, valued: 3, unvalued: 2 },
                }),
            }),
        );

        expect(
            screen.getByText('3 items priced · 2 not yet'),
        ).toBeInTheDocument();
    });

    it('shows friendly empty states for a range with no activity', () => {
        renderPage(
            <Dashboard />,
            props({
                kpis: kpis({
                    sales: { count: 0, amount: 0 },
                    production: { count: 0, quantity: 0 },
                }),
                snapshot: quietSnapshot({
                    stock_value: { amount: 0, valued: 0, unvalued: 0 },
                }),
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
