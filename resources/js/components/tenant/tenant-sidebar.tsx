import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRightLeft,
    Boxes,
    Contact,
    FolderTree,
    LayoutGrid,
    MapPin,
    Package,
    ShoppingCart,
    Truck,
    Warehouse,
} from 'lucide-react';
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
import { dashboard } from '@/routes/tenant';
import categories from '@/routes/tenant/categories';
import customers from '@/routes/tenant/customers';
import locations from '@/routes/tenant/locations';
import products from '@/routes/tenant/products';
import purchaseOrders from '@/routes/tenant/purchase-orders';
import rawMaterials from '@/routes/tenant/raw-materials';
import stockMovements from '@/routes/tenant/stock-movements';
import stockTransfers from '@/routes/tenant/stock-transfers';
import suppliers from '@/routes/tenant/suppliers';
import warehouses from '@/routes/tenant/warehouses';
import type { NavItem } from '@/types';

type TenantBrand = { slug: string; name: string } | null;

export function TenantSidebar() {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };
    const slug = tenant?.slug ?? '';
    const dashboardUrl = dashboard.url({ tenant: slug });

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
            prefetch: true,
        },
        {
            title: 'Categories',
            href: categories.index.url({ tenant: slug }),
            icon: FolderTree,
        },
        {
            title: 'Suppliers',
            href: suppliers.index.url({ tenant: slug }),
            icon: Truck,
        },
        {
            title: 'Customers',
            href: customers.index.url({ tenant: slug }),
            icon: Contact,
        },
        {
            title: 'Raw materials',
            href: rawMaterials.index.url({ tenant: slug }),
            icon: Boxes,
        },
        {
            title: 'Products',
            href: products.index.url({ tenant: slug }),
            icon: Package,
        },
        {
            title: 'Warehouses',
            href: warehouses.index.url({ tenant: slug }),
            icon: Warehouse,
        },
        {
            title: 'Locations',
            href: locations.index.url({ tenant: slug }),
            icon: MapPin,
        },
        {
            title: 'Stock movements',
            href: stockMovements.index.url({ tenant: slug }),
            icon: ArrowLeftRight,
        },
        {
            title: 'Stock transfers',
            href: stockTransfers.index.url({ tenant: slug }),
            icon: ArrowRightLeft,
        },
        {
            title: 'Purchase orders',
            href: purchaseOrders.index.url({ tenant: slug }),
            icon: ShoppingCart,
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
