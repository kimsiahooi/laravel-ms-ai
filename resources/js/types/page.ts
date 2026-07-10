/** Server-paginated list query state shared by every index page. */
export type ResourceFilters = {
    search: string;
    per_page: number;
};

/** The current tenant's identity, shared with every tenant page. */
export type TenantBrand = {
    slug: string;
    name: string;
};

/** One-shot success flash surfaced after a redirect. */
export type FlashSuccess = {
    success: string | null;
};

/**
 * Props every tenant catalog list page receives. Intersect it with the page's
 * own paginated rows, e.g. `TenantPageProps & { products: Paginator<Product> }`.
 */
export type TenantPageProps = {
    filters: ResourceFilters;
    tenant: TenantBrand;
    flash: FlashSuccess;
};
