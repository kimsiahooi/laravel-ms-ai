import type { LucideIcon } from 'lucide-react';
import { Boxes, Contact, FolderTree, Package, Truck } from 'lucide-react';

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
