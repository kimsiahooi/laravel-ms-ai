import {
    ArrowLeftRight,
    ArrowRightLeft,
    BarChart3,
    Boxes,
    Building2,
    ClipboardCheck,
    Contact,
    Factory,
    FolderTree,
    History,
    LayoutGrid,
    MapPin,
    Package,
    Receipt,
    RotateCcw,
    ShieldCheck,
    ShoppingCart,
    Truck,
    Undo2,
    UserRound,
    Warehouse,
} from 'lucide-react';
import { dashboard } from '@/routes/tenant';
import activity from '@/routes/tenant/activity';
import categories from '@/routes/tenant/categories';
import customers from '@/routes/tenant/customers';
import locations from '@/routes/tenant/locations';
import productionOrders from '@/routes/tenant/production-orders';
import products from '@/routes/tenant/products';
import purchaseOrders from '@/routes/tenant/purchase-orders';
import purchaseReturns from '@/routes/tenant/purchase-returns';
import rawMaterials from '@/routes/tenant/raw-materials';
import reports from '@/routes/tenant/reports';
import roles from '@/routes/tenant/roles';
import salesOrders from '@/routes/tenant/sales-orders';
import salesReturns from '@/routes/tenant/sales-returns';
import settingsRoutes from '@/routes/tenant/settings';
import stockMovements from '@/routes/tenant/stock-movements';
import stockTakes from '@/routes/tenant/stock-takes';
import stockTransfers from '@/routes/tenant/stock-transfers';
import suppliers from '@/routes/tenant/suppliers';
import users from '@/routes/tenant/users';
import warehouses from '@/routes/tenant/warehouses';
import type { NavGroup } from '@/types';

/**
 * The tenant workspace's labelled navigation — the single source of truth shared
 * by the sidebar ([`TenantSidebar`](../components/tenant/tenant-sidebar.tsx)) and
 * the ⌘K command palette. Each href is built from the live tenant slug via the
 * Wayfinder route helpers, so pass the current slug.
 *
 * Pass `can` (from [`usePermissions`](../hooks/use-permissions.ts)) to hide items
 * the signed-in user can't reach: any item with a `permission` is dropped when the
 * user lacks it, and a group left with no items is dropped too. Omit `can` to show
 * everything (the server still enforces access on every route).
 */
export function tenantNavGroups(
    slug: string,
    can?: (permission: string) => boolean,
): NavGroup[] {
    const groups: NavGroup[] = [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: slug }),
                    icon: LayoutGrid,
                    prefetch: true,
                },
            ],
        },
        {
            label: 'Catalog',
            items: [
                {
                    title: 'Categories',
                    href: categories.index.url({ tenant: slug }),
                    icon: FolderTree,
                    permission: 'categories.view',
                },
                {
                    title: 'Suppliers',
                    href: suppliers.index.url({ tenant: slug }),
                    icon: Truck,
                    permission: 'suppliers.view',
                },
                {
                    title: 'Customers',
                    href: customers.index.url({ tenant: slug }),
                    icon: Contact,
                    permission: 'customers.view',
                },
                {
                    title: 'Raw materials',
                    href: rawMaterials.index.url({ tenant: slug }),
                    icon: Boxes,
                    permission: 'raw-materials.view',
                },
                {
                    title: 'Products',
                    href: products.index.url({ tenant: slug }),
                    icon: Package,
                    permission: 'products.view',
                },
            ],
        },
        {
            label: 'Stock',
            items: [
                {
                    title: 'Locations',
                    href: locations.index.url({ tenant: slug }),
                    icon: MapPin,
                    permission: 'locations.view',
                },
                {
                    title: 'Warehouses',
                    href: warehouses.index.url({ tenant: slug }),
                    icon: Warehouse,
                    permission: 'warehouses.view',
                },
                {
                    title: 'Stock movements',
                    href: stockMovements.index.url({ tenant: slug }),
                    icon: ArrowLeftRight,
                    permission: 'stock-movements.view',
                },
                {
                    title: 'Stock transfers',
                    href: stockTransfers.index.url({ tenant: slug }),
                    icon: ArrowRightLeft,
                    permission: 'stock-transfers.view',
                },
                {
                    title: 'Stock takes',
                    href: stockTakes.index.url({ tenant: slug }),
                    icon: ClipboardCheck,
                    permission: 'stock-takes.view',
                },
            ],
        },
        {
            label: 'Orders',
            items: [
                {
                    title: 'Purchase orders',
                    href: purchaseOrders.index.url({ tenant: slug }),
                    icon: ShoppingCart,
                    permission: 'purchase-orders.view',
                },
                {
                    title: 'Purchase returns',
                    href: purchaseReturns.index.url({ tenant: slug }),
                    icon: Undo2,
                    permission: 'purchase-returns.view',
                },
                {
                    title: 'Sales orders',
                    href: salesOrders.index.url({ tenant: slug }),
                    icon: Receipt,
                    permission: 'sales-orders.view',
                },
                {
                    title: 'Sales returns',
                    href: salesReturns.index.url({ tenant: slug }),
                    icon: RotateCcw,
                    permission: 'sales-returns.view',
                },
                {
                    title: 'Production orders',
                    href: productionOrders.index.url({ tenant: slug }),
                    icon: Factory,
                    permission: 'production-orders.view',
                },
            ],
        },
        {
            label: 'Insights',
            items: [
                {
                    title: 'Reports',
                    href: reports.index.url({ tenant: slug }),
                    icon: BarChart3,
                    permission: 'reports.view',
                },
                {
                    title: 'Activity',
                    href: activity.index.url({ tenant: slug }),
                    icon: History,
                    permission: 'activity.view',
                },
            ],
        },
        {
            label: 'Team & access',
            items: [
                {
                    title: 'Users',
                    href: users.index.url({ tenant: slug }),
                    icon: UserRound,
                    permission: 'users.view',
                },
                {
                    title: 'Roles',
                    href: roles.index.url({ tenant: slug }),
                    icon: ShieldCheck,
                    permission: 'roles.view',
                },
            ],
        },
        {
            label: 'Settings',
            items: [
                {
                    title: 'Business settings',
                    href: settingsRoutes.edit.url({
                        tenant: slug,
                        category: 'business',
                    }),
                    icon: Building2,
                    permission: 'settings.view',
                },
            ],
        },
    ];

    if (!can) {
        return groups;
    }

    // Drop items the user can't reach, then any group left empty.
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
}
