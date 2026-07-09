import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ImageIcon,
    LoaderCircle,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Combobox } from '@/components/combobox';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import TenantLayout from '@/layouts/tenant-layout';

type Product = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    description: string | null;
    image_url: string | null;
    category_id: number | null;
    supplier_id: number | null;
    category: string | null;
    supplier: string | null;
    min_stock: number;
    unit: string;
    created_at: string;
};

type Option = { id: number; name: string };

type PageProps = {
    products: Paginator<Product>;
    filters: { search: string; per_page: number };
    categories: Option[];
    suppliers: Option[];
    tenant: { slug: string; name: string };
    flash: { success: string | null };
};

function flashToast(page: { props: unknown }): void {
    const message = (page.props as { flash?: { success?: string | null } })
        .flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function ProductsIndex() {
    const page = usePage();
    const { products, filters, categories, suppliers, tenant } =
        page.props as unknown as PageProps;
    const base = `/${tenant.slug}/products`;

    const categoryOptions = categories.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const supplierOptions = suppliers.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
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
    const [deleting, setDeleting] = useState<Product | null>(null);
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

    const openCreate = () => {
        setEditing(null);
        resetForm();
        setFormOpen(true);
    };

    const openEdit = (product: Product) => {
        setEditing(product);
        setName(product.name);
        setSku(product.sku);
        setBarcode(product.barcode ?? '');
        setUnit(product.unit);
        setMinStock(String(product.min_stock ?? 0));
        setCategoryId(product.category_id ? String(product.category_id) : '');
        setSupplierId(product.supplier_id ? String(product.supplier_id) : '');
        setDescription(product.description ?? '');
        setPreview(product.image_url);
        setRemoveImage(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        setFormOpen(true);
    };

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

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }
        router.delete(`${base}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: (deleted) => {
                setDeleting(null);
                flashToast(deleted);
            },
        });
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
            cell: ({ row }) => row.original.min_stock.toLocaleString(),
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
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Actions for ${row.original.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() => openEdit(row.original)}
                        >
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setDeleting(row.original)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
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
                    <Button onClick={openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New product
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Package className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No products yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Add your first product to start building
                                    your catalog.
                                </p>
                            </div>
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                New product
                            </Button>
                        </CardContent>
                    </Card>
                }
            />

            {/* Create / edit dialog */}
            <Dialog
                open={formOpen}
                onOpenChange={(next) => {
                    if (!next) {
                        setFormOpen(false);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit product' : 'New product'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this product.'
                                : 'Add a product to your catalog.'}
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        key={editing?.id ?? 'new'}
                        action={editing ? `${base}/${editing.id}` : base}
                        method={editing ? 'put' : 'post'}
                        disableWhileProcessing
                        onSuccess={(saved) => {
                            setFormOpen(false);
                            flashToast(saved);
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
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
                                            onChange={(e) =>
                                                setName(e.target.value)
                                            }
                                            required
                                            autoFocus
                                            placeholder="e.g. Widget A"
                                            aria-invalid={!!errors.name}
                                            aria-describedby={
                                                errors.name
                                                    ? 'name-error'
                                                    : undefined
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
                                            onChange={(e) =>
                                                setSku(e.target.value)
                                            }
                                            required
                                            placeholder="e.g. P-001"
                                            aria-invalid={!!errors.sku}
                                            aria-describedby={
                                                errors.sku
                                                    ? 'sku-error'
                                                    : undefined
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
                                            onChange={(e) =>
                                                setBarcode(e.target.value)
                                            }
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
                                            onChange={(e) =>
                                                setUnit(e.target.value)
                                            }
                                            required
                                            placeholder="e.g. pcs"
                                            aria-invalid={!!errors.unit}
                                            aria-describedby={
                                                errors.unit
                                                    ? 'unit-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="unit-error"
                                            role="alert"
                                            message={errors.unit}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="category">
                                            Category
                                        </Label>
                                        <Combobox
                                            id="category"
                                            options={categoryOptions}
                                            value={categoryId}
                                            onChange={setCategoryId}
                                            placeholder="Select category"
                                            searchPlaceholder="Search categories…"
                                            emptyText="No categories."
                                            invalid={!!errors.category_id}
                                            describedBy={
                                                errors.category_id
                                                    ? 'category-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="category-error"
                                            role="alert"
                                            message={errors.category_id}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="supplier">
                                            Supplier
                                        </Label>
                                        <Combobox
                                            id="supplier"
                                            options={supplierOptions}
                                            value={supplierId}
                                            onChange={setSupplierId}
                                            placeholder="Select supplier"
                                            searchPlaceholder="Search suppliers…"
                                            emptyText="No suppliers."
                                            invalid={!!errors.supplier_id}
                                            describedBy={
                                                errors.supplier_id
                                                    ? 'supplier-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="supplier-error"
                                            role="alert"
                                            message={errors.supplier_id}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="min_stock">
                                            Min stock
                                        </Label>
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
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={description}
                                        onChange={(e) =>
                                            setDescription(e.target.value)
                                        }
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

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <>
                                                <LoaderCircle className="size-4 animate-spin" />
                                                Saving…
                                            </>
                                        ) : editing ? (
                                            'Save changes'
                                        ) : (
                                            'Create product'
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete product</DialogTitle>
                        <DialogDescription>
                            Remove “{deleting?.name}” from your catalog? This
                            can be restored later.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
