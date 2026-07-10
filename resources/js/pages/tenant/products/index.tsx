import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ImageIcon, Package, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { flashToast } from '@/lib/flash';
import { formatQuantity } from '@/lib/format';
import type { TenantPageProps } from '@/types';

type Product = App.Data.ProductData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    products: Paginator<Product>;
    categories: Option[];
    suppliers: Option[];
};

export default function ProductsIndex() {
    const { products, filters, categories, suppliers, tenant } =
        usePageProps<PageProps>();
    const base = `/${tenant.slug}/products`;

    const categoryOptions = categories.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const supplierOptions = suppliers.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));

    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [barcode, setBarcode] = useState('');
    const [unit, setUnit] = useState('');
    const [minStock, setMinStock] = useState('0');
    const [categoryId, setCategoryId] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [description, setDescription] = useState('');
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [removeImage, setRemoveImage] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    // Route every preview change through here so a replaced blob: URL is revoked
    // (server image_url values are left alone). Prevents leaking the Blob.
    const previewRef = useRef<string | null>(null);
    const setPreview = (next: string | null) => {
        if (
            previewRef.current?.startsWith('blob:') &&
            previewRef.current !== next
        ) {
            URL.revokeObjectURL(previewRef.current);
        }
        previewRef.current = next;
        setImagePreview(next);
    };

    // Revoke any outstanding blob: URL when the page unmounts.
    useEffect(
        () => () => {
            if (previewRef.current?.startsWith('blob:')) {
                URL.revokeObjectURL(previewRef.current);
            }
        },
        [],
    );

    const resetForm = () => {
        setName('');
        setSku('');
        setBarcode('');
        setUnit('');
        setMinStock('0');
        setCategoryId('');
        setSupplierId('');
        setDescription('');
        setPreview(null);
        setRemoveImage(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const fillForm = (product: Product) => {
        setName(product.name);
        setSku(product.sku);
        setBarcode(product.barcode ?? '');
        setUnit(product.unit);
        setMinStock(String(product.min_stock ?? 0));
        const catId = product.category_id ? String(product.category_id) : '';
        setCategoryId(
            categoryOptions.some((o) => o.value === catId) ? catId : '',
        );
        const supId = product.supplier_id ? String(product.supplier_id) : '';
        setSupplierId(
            supplierOptions.some((o) => o.value === supId) ? supId : '',
        );
        setDescription(product.description ?? '');
        setPreview(product.image_url);
        setRemoveImage(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const dialog = useResourceDialog<Product>({
        onCreate: resetForm,
        onEdit: fillForm,
    });
    const del = useDelete<Product>({ baseUrl: base, onDeleted: flashToast });

    const onImageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            setPreview(URL.createObjectURL(file));
            setRemoveImage(false);
        }
    };

    const clearImage = () => {
        setPreview(null);
        setRemoveImage(true);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const columns: ColumnDef<Product>[] = [
        {
            id: 'image',
            header: () => <span className="sr-only">Image</span>,
            meta: { className: 'w-14' },
            cell: ({ row }) =>
                row.original.image_url ? (
                    <img
                        src={row.original.image_url}
                        alt=""
                        className="size-10 rounded-md border object-cover"
                    />
                ) : (
                    <span className="grid size-10 place-items-center rounded-md bg-secondary text-muted-foreground">
                        <ImageIcon className="size-4" />
                    </span>
                ),
        },
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'sku',
            header: 'SKU',
            cell: ({ row }) => (
                <span className="font-mono text-muted-foreground text-xs">
                    {row.original.sku}
                </span>
            ),
        },
        {
            accessorKey: 'category',
            header: 'Category',
            cell: ({ row }) => row.original.category ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
        {
            accessorKey: 'supplier',
            header: 'Supplier',
            cell: ({ row }) => row.original.supplier ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
        {
            accessorKey: 'min_stock',
            header: 'Min stock',
            cell: ({ row }) => formatQuantity(row.original.min_stock),
            meta: {
                className: 'text-right text-muted-foreground tabular-nums',
            },
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <RowActions
                    label={row.original.name}
                    onEdit={() => dialog.openEdit(row.original)}
                    onDelete={() => del.request(row.original)}
                />
            ),
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Products', href: base },
            ]}
        >
            <Head title="Products" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Products
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the finished goods in your catalog.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={products}
                filters={filters}
                baseUrl={base}
                only={['products', 'filters']}
                getRowId={(product) => String(product.id)}
                title="Products"
                searchPlaceholder="Search name, SKU or barcode…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New product
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={Package}
                        title="No products yet"
                        description="Add your first product to start building your catalog."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New product
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel="product"
                baseUrl={base}
                onSuccess={flashToast}
                contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            >
                {({ errors }) => (
                    <>
                        {/* Hidden inputs mirror combobox / remove state */}
                        <input
                            type="hidden"
                            name="category_id"
                            value={categoryId}
                        />
                        <input
                            type="hidden"
                            name="supplier_id"
                            value={supplierId}
                        />
                        {removeImage && (
                            <input
                                type="hidden"
                                name="remove_image"
                                value="1"
                            />
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    required
                                    autoFocus
                                    placeholder="e.g. Widget A"
                                    aria-invalid={!!errors.name}
                                    aria-describedby={
                                        errors.name ? 'name-error' : undefined
                                    }
                                />
                                <InputError
                                    id="name-error"
                                    role="alert"
                                    message={errors.name}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    name="sku"
                                    value={sku}
                                    onChange={(e) => setSku(e.target.value)}
                                    required
                                    placeholder="e.g. P-001"
                                    aria-invalid={!!errors.sku}
                                    aria-describedby={
                                        errors.sku ? 'sku-error' : undefined
                                    }
                                />
                                <InputError
                                    id="sku-error"
                                    role="alert"
                                    message={errors.sku}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="barcode">Barcode</Label>
                                <Input
                                    id="barcode"
                                    name="barcode"
                                    value={barcode}
                                    onChange={(e) => setBarcode(e.target.value)}
                                    placeholder="Optional"
                                    aria-invalid={!!errors.barcode}
                                    aria-describedby={
                                        errors.barcode
                                            ? 'barcode-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="barcode-error"
                                    role="alert"
                                    message={errors.barcode}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="unit">Unit</Label>
                                <Input
                                    id="unit"
                                    name="unit"
                                    value={unit}
                                    onChange={(e) => setUnit(e.target.value)}
                                    required
                                    placeholder="e.g. pcs"
                                    aria-invalid={!!errors.unit}
                                    aria-describedby={
                                        errors.unit ? 'unit-error' : undefined
                                    }
                                />
                                <InputError
                                    id="unit-error"
                                    role="alert"
                                    message={errors.unit}
                                />
                            </div>
                            <ComboboxField
                                id="category"
                                label="Category"
                                options={categoryOptions}
                                value={categoryId}
                                onChange={setCategoryId}
                                error={errors.category_id}
                                placeholder="Select category"
                                searchPlaceholder="Search categories…"
                                emptyText="No categories."
                            />
                            <ComboboxField
                                id="supplier"
                                label="Supplier"
                                options={supplierOptions}
                                value={supplierId}
                                onChange={setSupplierId}
                                error={errors.supplier_id}
                                placeholder="Select supplier"
                                searchPlaceholder="Search suppliers…"
                                emptyText="No suppliers."
                            />
                            <div className="space-y-2">
                                <Label htmlFor="min_stock">Min stock</Label>
                                <Input
                                    id="min_stock"
                                    name="min_stock"
                                    type="number"
                                    min={0}
                                    step="1"
                                    value={minStock}
                                    onChange={(e) =>
                                        setMinStock(e.target.value)
                                    }
                                    placeholder="0"
                                    aria-invalid={!!errors.min_stock}
                                    aria-describedby={
                                        errors.min_stock
                                            ? 'min_stock-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="min_stock-error"
                                    role="alert"
                                    message={errors.min_stock}
                                />
                            </div>
                        </div>

                        {/* Image */}
                        <div className="space-y-2">
                            <Label htmlFor="image">Image</Label>
                            <div className="flex items-center gap-3">
                                {imagePreview ? (
                                    <img
                                        src={imagePreview}
                                        alt=""
                                        className="size-16 rounded-md border object-cover"
                                    />
                                ) : (
                                    <span className="grid size-16 place-items-center rounded-md border border-dashed text-muted-foreground">
                                        <ImageIcon className="size-5" />
                                    </span>
                                )}
                                <div className="flex flex-col gap-2">
                                    <Input
                                        ref={fileRef}
                                        id="image"
                                        name="image"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={onImageChange}
                                        aria-invalid={!!errors.image}
                                        aria-describedby={
                                            errors.image
                                                ? 'image-error'
                                                : undefined
                                        }
                                    />
                                    {imagePreview && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="w-fit"
                                            onClick={clearImage}
                                        >
                                            <X className="size-4" />
                                            Remove image
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <InputError
                                id="image-error"
                                role="alert"
                                message={errors.image}
                            />
                        </div>

                        {/* Description */}
                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                name="description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                rows={3}
                                placeholder="Optional notes about this product."
                                aria-invalid={!!errors.description}
                                aria-describedby={
                                    errors.description
                                        ? 'description-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="description-error"
                                role="alert"
                                message={errors.description}
                            />
                        </div>
                    </>
                )}
            </ResourceFormDialog>

            <ConfirmDeleteDialog
                item={del.deleting}
                onOpenChange={(next) => {
                    if (!next) {
                        del.cancel();
                    }
                }}
                onConfirm={del.confirm}
                title="Delete product"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your catalog? This
                        can be restored later.
                    </>
                }
            />
        </TenantLayout>
    );
}
