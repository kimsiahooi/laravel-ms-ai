import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    // Opt-in Inertia prefetch. Off by default: prefetching list pages caches a
    // pre-mutation snapshot, so they look stale after a create/delete until a
    // hard refresh. Only enable on stable pages (e.g. the dashboard).
    prefetch?: boolean;
    // The permission name (e.g. `suppliers.view`) required to see this item.
    // Omit for items open to any signed-in user (e.g. the dashboard).
    permission?: string;
};

/** A labelled section of nav items (e.g. Catalog, Stock, Orders). */
export type NavGroup = {
    label?: string;
    items: NavItem[];
};
