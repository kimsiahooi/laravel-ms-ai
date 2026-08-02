import type { Paginator } from '@/components/data-table';

/**
 * Shared page-prop fixtures for the render-smoke tests. Keeps each page test to its
 * own data shape while the common tenant/auth props + the paginator envelope live
 * here once.
 */

// Every permission name, mirroring app/Support/TenantPermissions. Page tests
// default to an Administrator (full access) so gated create/edit/delete buttons
// render; pass an `auth` override to test the limited-permission case.
const SCREENS: Record<string, string[]> = {
    categories: ['view', 'create', 'update', 'delete'],
    suppliers: ['view', 'create', 'update', 'delete'],
    customers: ['view', 'create', 'update', 'delete'],
    'raw-materials': ['view', 'create', 'update', 'delete'],
    products: ['view', 'create', 'update', 'delete'],
    locations: ['view', 'create', 'update', 'delete'],
    warehouses: ['view', 'create', 'update', 'delete'],
    'stock-movements': ['view', 'create'],
    'stock-transfers': ['view', 'create'],
    'stock-takes': ['view', 'create', 'delete'],
    'purchase-orders': ['view', 'create', 'update', 'delete'],
    'purchase-returns': ['view', 'create', 'update', 'delete'],
    'sales-orders': ['view', 'create', 'update', 'delete'],
    'sales-returns': ['view', 'create', 'update', 'delete'],
    'production-orders': ['view', 'create', 'delete'],
    reports: ['view'],
    activity: ['view'],
    users: ['view', 'create', 'update', 'delete'],
    roles: ['view', 'create', 'update', 'delete'],
    settings: ['view', 'update'],
};

export const ALL_PERMISSIONS: string[] = Object.entries(SCREENS).flatMap(
    ([screen, actions]) => actions.map((action) => `${screen}.${action}`),
);

/** The always-present tenant page props (auth, tenant, organization). */
export function tenantProps(overrides: Record<string, unknown> = {}) {
    return {
        auth: {
            user: { name: 'Ada Lovelace', email: 'ada@acme.test' },
            permissions: ALL_PERMISSIONS,
            is_admin: true,
        },
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
