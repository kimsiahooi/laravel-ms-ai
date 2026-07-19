import { Link, usePage } from '@inertiajs/react';
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
import { tenantNavGroups } from '@/config/tenant-nav';
import { dashboard } from '@/routes/tenant';

type TenantBrand = { slug: string; name: string } | null;

export function TenantSidebar() {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };
    const slug = tenant?.slug ?? '';
    const dashboardUrl = dashboard.url({ tenant: slug });

    const navGroups = tenantNavGroups(slug);

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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <TenantNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
