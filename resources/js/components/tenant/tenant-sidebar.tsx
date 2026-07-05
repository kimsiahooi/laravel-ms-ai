import { Link, usePage } from '@inertiajs/react';
import { Contact, FolderTree, LayoutGrid, Truck } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import TenantLogo from '@/components/tenant/tenant-logo';
import { TenantNavUser } from '@/components/tenant/tenant-nav-user';
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

type TenantBrand = { slug: string; name: string } | null;

export function TenantSidebar() {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };
    const slug = tenant?.slug ?? '';
    const dashboardUrl = `/${slug}/dashboard`;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Categories',
            href: `/${slug}/categories`,
            icon: FolderTree,
        },
        {
            title: 'Suppliers',
            href: `/${slug}/suppliers`,
            icon: Truck,
        },
        {
            title: 'Customers',
            href: `/${slug}/customers`,
            icon: Contact,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <TenantLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <TenantNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
