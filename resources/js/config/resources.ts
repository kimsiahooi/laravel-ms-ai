import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeftRight,
    ArrowRightLeft,
    Boxes,
    Contact,
    FolderTree,
    MapPin,
    Package,
    ShoppingCart,
    Truck,
    Warehouse,
} from 'lucide-react';

/**
 * Display metadata for a catalog resource — one home for its label + icon so the
 * page title, breadcrumb, toolbar button, entity label, and empty state all read
 * from the same source instead of repeating the wording. Bespoke copy (e.g. an
 * empty-state description) stays inline on the page.
 */
export type ResourceMeta = {
    /** Lowercase singular, e.g. "raw material" → "New raw material". */
    singular: string;
    /** Title-case plural, e.g. "Raw materials" → page title / breadcrumb. */
    plural: string;
    icon: LucideIcon;
};

export const rawMaterialMeta: ResourceMeta = {
    singular: 'raw material',
    plural: 'Raw materials',
    icon: Boxes,
};

export const categoryMeta: ResourceMeta = {
    singular: 'category',
    plural: 'Categories',
    icon: FolderTree,
};

export const supplierMeta: ResourceMeta = {
    singular: 'supplier',
    plural: 'Suppliers',
    icon: Truck,
};

export const customerMeta: ResourceMeta = {
    singular: 'customer',
    plural: 'Customers',
    icon: Contact,
};

export const productMeta: ResourceMeta = {
    singular: 'product',
    plural: 'Products',
    icon: Package,
};

export const warehouseMeta: ResourceMeta = {
    singular: 'warehouse',
    plural: 'Warehouses',
    icon: Warehouse,
};

export const locationMeta: ResourceMeta = {
    singular: 'location',
    plural: 'Locations',
    icon: MapPin,
};

export const stockMovementMeta: ResourceMeta = {
    singular: 'movement',
    plural: 'Stock movements',
    icon: ArrowLeftRight,
};

export const stockTransferMeta: ResourceMeta = {
    singular: 'transfer',
    plural: 'Stock transfers',
    icon: ArrowRightLeft,
};

export const purchaseOrderMeta: ResourceMeta = {
    singular: 'purchase order',
    plural: 'Purchase orders',
    icon: ShoppingCart,
};
