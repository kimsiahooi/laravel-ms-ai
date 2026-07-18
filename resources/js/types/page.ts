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

/**
 * Props every tenant catalog list page receives. Intersect it with the page's
 * own paginated rows, e.g. `TenantPageProps & { products: Paginator<Product> }`.
 * (Success toasts are flashed by the backend and rendered by the global
 * `useFlashToast` hook, so there is no per-page `flash` prop.)
 */
export type TenantPageProps = {
    filters: ResourceFilters;
    tenant: TenantBrand;
    /** The tenant's company profile, shared for document headers (null until set). */
    business?: App.Data.BusinessSettingsData | null;
};
