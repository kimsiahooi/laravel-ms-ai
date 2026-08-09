import { fireEvent, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import {
    renderPage,
    renderPageWithForm,
    submitForm,
    submittedVisits,
} from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SalesOrdersIndex } = await import(
    '@/pages/tenant/sales-orders'
);

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        orders: paginator([
            {
                id: 1,
                customer: 'Globex Retail',
                status: 'pending',
                status_label: 'Pending',
                currency: 'MYR',
                item_count: 1,
                total: 20,
                fulfilled_at: null,
                created_at: '2026-07-01T00:00:00+08:00',
                items: [],
            },
        ]),
        customers: [],
        products: [],
        warehouses: [],
        baseCurrency: 'MYR',
        currencies: ['MYR', 'USD'],
        filters: filters(),
        ...overrides,
    };
}

describe('sales orders index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<SalesOrdersIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Sales orders' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Globex Retail')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no orders', () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                orders: paginator([]),
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
            }),
        );

        expect(screen.getByText(/no sales orders yet/i)).toBeInTheDocument();
    });

    it('guides you to add prerequisites and disables New when none exist', () => {
        renderPage(
            <SalesOrdersIndex />,
            props({ orders: paginator([]), customers: [], products: [] }),
        );

        expect(
            screen.getByText(/before you can create a sales order/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /add a customer/i }),
        ).toBeInTheDocument();
    });

    it('offers a per-line taxable toggle when the tenant charges tax (R15)', async () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
                business: { tax_type: 'sst', tax_rate: 10 },
            }),
        );

        fireEvent.click(
            screen.getByRole('button', { name: /new sales order/i }),
        );

        // The new line defaults to taxable; the checkbox is checked and labelled.
        const taxable = await screen.findByRole('checkbox');
        expect(taxable).toHaveAttribute('aria-checked', 'true');
        expect(screen.getByText('Taxable')).toBeInTheDocument();
    });

    it('surfaces a rejected order number on the create form', async () => {
        renderPageWithForm(
            <SalesOrdersIndex />,
            props({
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
            }),
            // Target the dialog's own form by its action, so the page's separate
            // fulfil/cancel forms are left alone.
            (action) =>
                action === '/acme/sales-orders'
                    ? {
                          errors: {
                              number: 'The number has already been taken.',
                          },
                      }
                    : undefined,
        );

        fireEvent.click(
            screen.getByRole('button', { name: /new sales order/i }),
        );

        expect(
            await screen.findByText('The number has already been taken.'),
        ).toBeInTheDocument();
        // By id — "order number" also matches the list's Order # column header.
        expect(document.querySelector('#number')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });

    it('hides the taxable toggle when the tenant charges no tax (R15)', async () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
                business: { tax_type: 'none', tax_rate: 0 },
            }),
        );

        fireEvent.click(
            screen.getByRole('button', { name: /new sales order/i }),
        );

        // Line items still render, but no taxable column.
        expect(await screen.findByText('Line items')).toBeInTheDocument();
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    });

    it('refuses a price finer than the column keeps, and says so on that line (zod)', async () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
            }),
        );

        fireEvent.click(
            screen.getByRole('button', { name: /new sales order/i }),
        );

        const price = await screen.findByLabelText('Unit price');
        // decimal(15,4) would keep 1.1235 — a different price from this one.
        fireEvent.change(price, { target: { value: '1.12345' } });
        submitForm(price);

        // Nothing was sent…
        expect(submittedVisits()).not.toHaveBeenCalled();
        // …and the message is on the line it belongs to, not a toast.
        expect(price).toHaveAttribute('aria-invalid', 'true');
        expect(
            document.getElementById(
                price.getAttribute('aria-describedby') ?? '',
            ),
        ).toHaveTextContent('may not have more than 4 decimal places');
    });

    it('refuses a quantity that would round away to nothing (zod)', async () => {
        renderPage(
            <SalesOrdersIndex />,
            props({
                customers: [{ id: 1, name: 'Globex Retail' }],
                products: [{ id: 2, name: 'Widget' }],
            }),
        );

        fireEvent.click(
            screen.getByRole('button', { name: /new sales order/i }),
        );

        const qty = await screen.findByLabelText('Quantity');
        // Passes "greater than zero" but reaches the column as 0.0000.
        fireEvent.change(qty, { target: { value: '0.00004' } });
        submitForm(qty);

        expect(submittedVisits()).not.toHaveBeenCalled();
        expect(qty).toHaveAttribute('aria-invalid', 'true');
    });
});
