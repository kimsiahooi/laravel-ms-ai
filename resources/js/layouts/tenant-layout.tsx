import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { TenantSidebar } from '@/components/tenant/tenant-sidebar';
import { TenantSidebarHeader } from '@/components/tenant/tenant-sidebar-header';
import { dashboard } from '@/routes/tenant';
import type { BreadcrumbItem } from '@/types';

type TenantBrand = { slug: string; name: string } | null;

/**
 * Shell for a tenant workspace (/{slug}/*). A shadcn inset sidebar mirroring the
 * central admin shell — imported directly by tenant pages (the Inertia layout
 * resolver returns null for tenant/*).
 */
export default function TenantLayout({
    children,
    breadcrumbs,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };

    const crumbs =
        breadcrumbs ??
        (tenant
            ? [
                  {
                      title: 'Dashboard',
                      href: dashboard.url({ tenant: tenant.slug }),
                  },
              ]
            : []);

    return (
        <AppShell variant="sidebar">
            <TenantSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <TenantSidebarHeader breadcrumbs={crumbs} />
                <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
