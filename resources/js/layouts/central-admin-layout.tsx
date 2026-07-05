import type { ReactNode } from 'react';
import { AdminSidebar } from '@/components/admin/admin-sidebar';
import { AdminSidebarHeader } from '@/components/admin/admin-sidebar-header';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import type { BreadcrumbItem } from '@/types';

const defaultBreadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
];

/**
 * Shell for the central super-admin area (/admin/*). A shadcn inset sidebar,
 * imported directly by each admin page — NOT wired through the Inertia layout
 * resolver in app.tsx, which deliberately returns null for admin/* so pages can
 * opt into this shell.
 */
export default function CentralAdminLayout({
    children,
    breadcrumbs = defaultBreadcrumbs,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AdminSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
