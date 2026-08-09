import { router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DataTable } from '@/components/data-table';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

type Row = { id: number; name: string };

const columns: ColumnDef<Row>[] = [{ accessorKey: 'name', header: 'Name' }];

function table(overrides: Record<string, unknown> = {}) {
    return (
        <DataTable<Row>
            columns={columns}
            paginator={paginator([{ id: 1, name: 'Acme Metals' }])}
            filters={filters()}
            baseUrl="/acme/suppliers"
            only={['suppliers']}
            getRowId={(row) => String(row.id)}
            title="Suppliers"
            emptyState={<p>No suppliers yet</p>}
            {...overrides}
        />
    );
}

afterEach(() => {
    vi.mocked(router.get).mockReset();
    vi.useRealTimers();
});

describe('data table', () => {
    it('lists the rows it was given', () => {
        renderPage(table(), tenantProps());

        expect(screen.getByText('Acme Metals')).toBeInTheDocument();
        expect(screen.getByText('Suppliers')).toBeInTheDocument();
    });

    it('shows the first-class empty state when nothing exists at all', () => {
        renderPage(
            table({ paginator: paginator([]), filters: filters() }),
            tenantProps(),
        );

        expect(screen.getByText('No suppliers yet')).toBeInTheDocument();
        // "Nothing here yet" is a different situation from "your search found
        // nothing": the empty state replaces the whole table, search box included.
        expect(screen.queryByText(/No results match/)).not.toBeInTheDocument();
        expect(
            screen.queryByRole('textbox', { name: 'Search' }),
        ).not.toBeInTheDocument();
    });

    it('shows a no-results panel, not the empty state, when a search matches nothing', () => {
        renderPage(
            table({ paginator: paginator([]), filters: filters('zzz') }),
            tenantProps(),
        );

        expect(screen.queryByText('No suppliers yet')).not.toBeInTheDocument();
        // Twice on purpose: the visible panel and the screen-reader status line.
        expect(screen.getAllByText(/No results match/)).toHaveLength(2);
        expect(
            screen.getAllByRole('button', { name: 'Clear search' }).length,
        ).toBeGreaterThan(0);
    });

    it('announces an empty result to a screen reader, not just visually', () => {
        renderPage(
            table({ paginator: paginator([]), filters: filters('zzz') }),
            tenantProps(),
        );

        expect(screen.getByRole('status')).toHaveTextContent(
            'No results match "zzz"',
        );
    });

    it('drops the search term when the clear action is used', () => {
        vi.useFakeTimers();
        renderPage(
            table({ paginator: paginator([]), filters: filters('zzz') }),
            tenantProps(),
        );

        // The panel's own CTA (the search box has its own clear affordance too).
        const clearButtons = screen.getAllByRole('button', {
            name: 'Clear search',
        });
        fireEvent.click(clearButtons[clearButtons.length - 1]);

        // Clearing only resets the box; the refetch still goes through the debounce.
        act(() => {
            vi.advanceTimersByTime(400);
        });

        expect(router.get).toHaveBeenCalledWith(
            '/acme/suppliers',
            // An empty term is dropped from the query string rather than sent blank.
            expect.objectContaining({ search: undefined }),
            // Reloading only the list prop keeps the rest of the page untouched.
            expect.objectContaining({
                only: ['suppliers'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        );
    });

    it('marks the table busy while a search request is in flight, and clears it after', () => {
        vi.useFakeTimers();
        let finish = () => {};
        vi.mocked(router.get).mockImplementation(
            (_url: unknown, _data?: unknown, options?: unknown) => {
                const cbs = options as
                    | { onStart?: () => void; onFinish?: () => void }
                    | undefined;
                cbs?.onStart?.();
                finish = () => cbs?.onFinish?.();
            },
        );

        const { container } = renderPage(table(), tenantProps());
        const busy = () => container.querySelector('[aria-busy="true"]');

        fireEvent.change(screen.getByRole('textbox', { name: 'Search' }), {
            target: { value: 'fan' },
        });
        // The search is debounced, so nothing is in flight until the timer fires.
        expect(busy()).toBeNull();

        act(() => {
            vi.advanceTimersByTime(400);
        });
        expect(busy()).toBeInTheDocument();

        // And the busy state must be released — otherwise the table stays dimmed
        // and unclickable for good after the first search.
        act(() => {
            finish();
        });
        expect(busy()).toBeNull();
    });
});
