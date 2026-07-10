import { Link } from '@inertiajs/react';
import { Archive, Building2, LayoutGrid } from 'lucide-react';
import AdminLogo from '@/components/admin/admin-logo';
import { AdminNavUser } from '@/components/admin/admin-nav-user';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import {
    index as tenantsIndex,
    trashed as tenantsTrashed,
} from '@/routes/admin/tenants';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        prefetch: true,
    },
    {
        title: 'Tenants',
        href: tenantsIndex(),
        icon: Building2,
    },
    {
        title: 'Archived',
        href: tenantsTrashed(),
        icon: Archive,
    },
];

export function AdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AdminLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <AdminNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
