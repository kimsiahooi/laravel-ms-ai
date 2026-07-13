import type { Paginator } from '@/components/data-table';

/**
 * Shared page-prop fixtures for the render-smoke tests. Keeps each page test to its
 * own data shape while the common tenant/auth props + the paginator envelope live
 * here once.
 */

/** The always-present tenant page props (auth, tenant, organization). */
export function tenantProps(overrides: Record<string, unknown> = {}) {
    return {
        auth: { user: { name: 'Ada Lovelace', email: 'ada@acme.test' } },
        tenant: { slug: 'acme', name: 'Acme' },
        organization: { name: 'Acme', slug: 'acme', logo: null },
        ...overrides,
    };
}

/** A Laravel paginator envelope wrapping `data`, with sensible single-page defaults. */
export function paginator<T>(
    data: T[],
    overrides: Partial<Paginator<T>> = {},
): Paginator<T> {
    const total = overrides.total ?? data.length;

    return {
        data,
        from: data.length ? 1 : null,
        to: data.length,
        total,
        current_page: 1,
        last_page: 1,
        per_page: 10,
        prev_page_url: null,
        next_page_url: null,
        links: [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '#', label: '1', active: true },
            { url: null, label: 'Next &raquo;', active: false },
        ],
        ...overrides,
    };
}

/** The `filters` prop the DataTable reads (search term + page size). */
export function filters(search = '', perPage = 10) {
    return { search, per_page: perPage };
}
