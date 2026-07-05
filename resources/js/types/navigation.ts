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
};
