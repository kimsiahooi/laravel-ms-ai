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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
        prefetch: true,
    },
    {
        title: 'Tenants',
        href: '/admin/tenants',
        icon: Building2,
    },
    {
        title: 'Archived',
        href: '/admin/tenants/trashed',
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
                            <Link href="/admin/dashboard" prefetch>
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
