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
    ShoppingCart,
    Truck,
    Undo2,
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
import salesOrders from '@/routes/tenant/sales-orders';
import salesReturns from '@/routes/tenant/sales-returns';
import settingsRoutes from '@/routes/tenant/settings';
import stockMovements from '@/routes/tenant/stock-movements';
import stockTakes from '@/routes/tenant/stock-takes';
import stockTransfers from '@/routes/tenant/stock-transfers';
import suppliers from '@/routes/tenant/suppliers';
import warehouses from '@/routes/tenant/warehouses';
import type { NavGroup } from '@/types';

/**
 * The tenant workspace's labelled navigation — the single source of truth shared
 * by the sidebar ([`TenantSidebar`](../components/tenant/tenant-sidebar.tsx)) and
 * the ⌘K command palette. Each href is built from the live tenant slug via the
 * Wayfinder route helpers, so pass the current slug.
 */
export function tenantNavGroups(slug: string): NavGroup[] {
    return [
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
            ],
        },
        {
            label: 'Stock',
            items: [
                {
                    title: 'Locations',
                    href: locations.index.url({ tenant: slug }),
                    icon: MapPin,
                },
                {
                    title: 'Warehouses',
                    href: warehouses.index.url({ tenant: slug }),
                    icon: Warehouse,
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
                    title: 'Stock takes',
                    href: stockTakes.index.url({ tenant: slug }),
                    icon: ClipboardCheck,
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
                },
                {
                    title: 'Purchase returns',
                    href: purchaseReturns.index.url({ tenant: slug }),
                    icon: Undo2,
                },
                {
                    title: 'Sales orders',
                    href: salesOrders.index.url({ tenant: slug }),
                    icon: Receipt,
                },
                {
                    title: 'Sales returns',
                    href: salesReturns.index.url({ tenant: slug }),
                    icon: RotateCcw,
                },
                {
                    title: 'Production orders',
                    href: productionOrders.index.url({ tenant: slug }),
                    icon: Factory,
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
                },
                {
                    title: 'Activity',
                    href: activity.index.url({ tenant: slug }),
                    icon: History,
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
                },
            ],
        },
    ];
}
