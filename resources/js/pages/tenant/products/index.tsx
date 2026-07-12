import { Head, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ImageIcon,
    ListTree,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Button } from '@/components/ui/button';
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
import { productMeta } from '@/config/resources';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import productsRoutes from '@/routes/tenant/products';
import type { TenantPageProps } from '@/types';

type Product = App.Data.ProductData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    products: Paginator<Product>;
    categories: Option[];
    suppliers: Option[];
    rawMaterials: Option[];
};

// One BOM line in the BOM editor. `key` is a stable client id so React keeps
// input focus across add/remove; the empty string means "not yet chosen".
type BomLine = { key: number; rawMaterialId: string; quantity: string };

export default function ProductsIndex() {
    const { products, filters, categories, suppliers, rawMaterials, tenant } =
        usePageProps<PageProps>();
    const base = productsRoutes.index.url({ tenant: tenant.slug });

    const categoryOptions = categories.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const supplierOptions = suppliers.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));
    const rawMaterialOptions = rawMaterials.map((m) => ({
        value: String(m.id),
        label: m.name,
    }));

    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [barcode, setBarcode] = useState('');
    const [unit, setUnit] = useState('');
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
    const del = useDelete<Product>({ baseUrl: base });

    // BOM editor. `bomProduct` doubles as the open/closed flag; its
    // BOM is edited as a repeater, keyed by a client-side counter so focus and
    // order survive add/remove.
    const [bomProduct, setBomProduct] = useState<Product | null>(null);
    const [bomLines, setBomLines] = useState<BomLine[]>([]);
    const bomForm = useForm<{
        items: { raw_material_id: number; quantity: string }[];
    }>({ items: [] });
    const bomErrors = bomForm.errors as Record<string, string>;
    const bomKey = useRef(0);

    const blankBomLine = (): BomLine => ({
        key: bomKey.current++,
        rawMaterialId: '',
        quantity: '1',
    });

    const openBom = (product: Product) => {
        bomForm.clearErrors();
        setBomProduct(product);
        setBomLines(
            product.bom.length > 0
                ? product.bom.map((item) => ({
                      key: bomKey.current++,
                      rawMaterialId: String(item.raw_material_id),
                      quantity: String(item.quantity),
                  }))
                : [blankBomLine()],
        );
    };

    const closeBom = () => {
        setBomProduct(null);
        bomForm.clearErrors();
    };

    const addBomLine = () => setBomLines((prev) => [...prev, blankBomLine()]);
    const removeBomLine = (key: number) =>
        setBomLines((prev) => prev.filter((line) => line.key !== key));
    const updateBomLine = (key: number, patch: Partial<BomLine>) =>
        setBomLines((prev) =>
            prev.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );

    // Raw materials already chosen in the BOM. Each material can be picked
    // only once: other rows filter it out, and the "Add material" button hides
    // once every material is already in the BOM.
    const usedRawMaterialIds = new Set(
        bomLines
            .filter((line) => line.rawMaterialId !== '')
            .map((line) => line.rawMaterialId),
    );
    const allRawMaterialsUsed =
        usedRawMaterialIds.size >= rawMaterialOptions.length;

    const saveBom = () => {
        if (!bomProduct) {
            return;
        }
        // Drop rows the user left without a raw material; the backend still
        // validates quantity + duplicates on what remains.
        bomForm.transform(() => ({
            items: bomLines
                .filter((line) => line.rawMaterialId !== '')
                .map((line) => ({
                    raw_material_id: Number(line.rawMaterialId),
                    quantity: line.quantity,
                })),
        }));

        bomForm.put(
            productsRoutes.bom.url({
                tenant: tenant.slug,
                product: bomProduct.id,
            }),
            {
                preserveScroll: true,
                onSuccess: closeBom,
            },
        );
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
                            className="size-8 text-muted-foreground"
                            aria-label={`Actions for ${row.original.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() => dialog.openEdit(row.original)}
                        >
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => openBom(row.original)}
                        >
                            <ListTree className="size-4" />
                            BOM
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => del.request(row.original)}
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
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: productMeta.plural, href: base },
            ]}
        >
            <Head title={productMeta.plural} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {productMeta.plural}
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
                title={productMeta.plural}
                searchPlaceholder="Search name, SKU or barcode…"
                renderExpanded={(product) => (
                    <div className="px-4 py-3">
                        {product.bom.length > 0 ? (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="font-medium text-sm">
                                        Bill of materials
                                        <span className="ml-1 font-normal text-muted-foreground">
                                            · to make one unit
                                        </span>
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openBom(product)}
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </Button>
                                </div>
                                <ul className="divide-y rounded-md border bg-background">
                                    {product.bom.map((line) => (
                                        <li
                                            key={line.id}
                                            className="flex items-center justify-between gap-2 px-3 py-1.5 text-sm"
                                        >
                                            <span className="text-foreground">
                                                {line.name}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">
                                                {formatQuantity(line.quantity)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : (
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-muted-foreground text-sm">
                                    No BOM yet — set the raw materials it takes
                                    to make this product.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openBom(product)}
                                >
                                    <ListTree className="size-4" />
                                    Set BOM
                                </Button>
                            </div>
                        )}
                    </div>
                )}
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New {productMeta.singular}
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={productMeta.icon}
                        title={`No ${productMeta.plural.toLowerCase()} yet`}
                        description="Add your first product to start building your catalog."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New {productMeta.singular}
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel={productMeta.singular}
                baseUrl={base}
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
                                <FieldLabel
                                    htmlFor="sku"
                                    hint="A unique code you assign to identify this product — it appears on labels, orders, and stock lists."
                                >
                                    SKU
                                </FieldLabel>
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
                                <FieldLabel
                                    htmlFor="barcode"
                                    hint="The barcode number printed on the item's packaging. Leave it blank if you don't track barcodes."
                                >
                                    Barcode
                                </FieldLabel>
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
                                <FieldLabel
                                    htmlFor="unit"
                                    hint="The unit you count this item in, such as “ea” (each), “kg”, or “box”. It's shown wherever quantities appear."
                                >
                                    Unit
                                </FieldLabel>
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

            <Dialog
                open={bomProduct !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        closeBom();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>BOM</DialogTitle>
                        <DialogDescription>
                            The raw materials and how much of each it takes to
                            make one “{bomProduct?.name}”. New production orders
                            save a copy of this BOM.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Scroll the rows here (not on DialogContent) so an open
                        combobox dropdown can float over the viewport instead of
                        being clipped by the dialog's overflow. The max-height caps
                        the whole dialog (header + body + footer ≈ 13rem of chrome)
                        to the viewport, so a short viewport can't push the header
                        out of reach now that DialogContent no longer scrolls. */}
                    <div
                        className="-mr-2 space-y-4 overflow-y-auto pr-2"
                        style={{ maxHeight: 'min(60vh, calc(90vh - 13rem))' }}
                    >
                        {bomErrors.items ? (
                            <p className="text-destructive text-sm">
                                {bomErrors.items}
                            </p>
                        ) : null}

                        {rawMaterialOptions.length === 0 ? (
                            <p className="rounded-md border border-dashed p-4 text-center text-muted-foreground text-sm">
                                Add raw materials first — there are none to
                                build a BOM from yet.
                            </p>
                        ) : (
                            <>
                                <div className="space-y-3">
                                    {(() => {
                                        // Blank rows are dropped before submit, so
                                        // map each visible row to its submitted index
                                        // to line server errors up with the right row.
                                        let submitted = -1;
                                        return bomLines.map((line) => {
                                            const idx =
                                                line.rawMaterialId !== ''
                                                    ? ++submitted
                                                    : -1;
                                            return (
                                                <div
                                                    key={line.key}
                                                    className="grid grid-cols-[1fr_auto_auto] items-end gap-2"
                                                >
                                                    <ComboboxField
                                                        id={`bom-${line.key}`}
                                                        label="Raw material"
                                                        options={rawMaterialOptions.filter(
                                                            (o) =>
                                                                o.value ===
                                                                    line.rawMaterialId ||
                                                                !usedRawMaterialIds.has(
                                                                    o.value,
                                                                ),
                                                        )}
                                                        value={
                                                            line.rawMaterialId
                                                        }
                                                        onChange={(value) =>
                                                            updateBomLine(
                                                                line.key,
                                                                {
                                                                    rawMaterialId:
                                                                        value,
                                                                },
                                                            )
                                                        }
                                                        error={
                                                            idx >= 0
                                                                ? bomErrors[
                                                                      `items.${idx}.raw_material_id`
                                                                  ]
                                                                : undefined
                                                        }
                                                        placeholder="Select material"
                                                        searchPlaceholder="Search materials…"
                                                        emptyText="No raw materials."
                                                    />
                                                    <div className="w-28 space-y-2">
                                                        <Label
                                                            htmlFor={`bom-qty-${line.key}`}
                                                            className="text-muted-foreground text-xs"
                                                        >
                                                            Quantity per unit
                                                        </Label>
                                                        <Input
                                                            id={`bom-qty-${line.key}`}
                                                            type="number"
                                                            min={0}
                                                            step="any"
                                                            value={
                                                                line.quantity
                                                            }
                                                            onChange={(event) =>
                                                                updateBomLine(
                                                                    line.key,
                                                                    {
                                                                        quantity:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            aria-invalid={
                                                                idx >= 0 &&
                                                                !!bomErrors[
                                                                    `items.${idx}.quantity`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-9 text-muted-foreground"
                                                        onClick={() =>
                                                            removeBomLine(
                                                                line.key,
                                                            )
                                                        }
                                                        disabled={
                                                            bomLines.length ===
                                                            1
                                                        }
                                                        aria-label="Remove material"
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                </div>
                                            );
                                        });
                                    })()}
                                </div>

                                {!allRawMaterialsUsed && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addBomLine}
                                    >
                                        <Plus className="size-4" />
                                        Add material
                                    </Button>
                                )}
                            </>
                        )}
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={saveBom}
                            disabled={
                                bomForm.processing ||
                                rawMaterialOptions.length === 0
                            }
                        >
                            {bomForm.processing ? 'Saving…' : 'Save BOM'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
