import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { FolderTree, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDelete } from '@/hooks/use-delete';
import { usePageProps } from '@/hooks/use-page-props';
import { useResourceDialog } from '@/hooks/use-resource-dialog';
import TenantLayout from '@/layouts/tenant-layout';
import { flashToast } from '@/lib/flash';
import type { TenantPageProps } from '@/types';

type Category = App.Data.CategoryData;

type PageProps = TenantPageProps & {
    categories: Paginator<Category>;
};

export default function CategoriesIndex() {
    const { categories, filters, tenant } = usePageProps<PageProps>();
    const base = `/${tenant.slug}/categories`;

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');

    const dialog = useResourceDialog<Category>({
        onCreate: () => {
            setName('');
            setDescription('');
        },
        onEdit: (category) => {
            setName(category.name);
            setDescription(category.description ?? '');
        },
    });

    const del = useDelete<Category>({
        baseUrl: base,
        onDeleted: flashToast,
    });

    const columns: ColumnDef<Category>[] = [
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
            accessorKey: 'description',
            header: 'Description',
            cell: ({ row }) => row.original.description ?? '—',
            meta: {
                className:
                    'hidden max-w-md truncate text-muted-foreground sm:table-cell',
            },
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
                { title: 'Categories', href: base },
            ]}
        >
            <Head title="Categories" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Categories
                </h1>
                <p className="text-muted-foreground text-sm">
                    Group the products in your catalog.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={categories}
                filters={filters}
                baseUrl={base}
                only={['categories', 'filters']}
                getRowId={(category) => String(category.id)}
                title="Categories"
                searchPlaceholder="Search name or description…"
                toolbar={
                    <Button onClick={dialog.openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New category
                    </Button>
                }
                emptyState={
                    <EmptyState
                        icon={FolderTree}
                        title="No categories yet"
                        description="Create your first category to start organizing your products."
                        action={
                            <Button onClick={dialog.openCreate}>
                                <Plus className="size-4" />
                                New category
                            </Button>
                        }
                    />
                }
            />

            <ResourceFormDialog
                open={dialog.open}
                onOpenChange={dialog.onOpenChange}
                editing={dialog.editing}
                entityLabel="category"
                baseUrl={base}
                onSuccess={flashToast}
                description={{
                    create: 'Add a category to organize your products.',
                    edit: 'Update this category.',
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                required
                                autoFocus
                                placeholder="e.g. Fasteners"
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
                            <Label htmlFor="description">
                                Description{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="description"
                                name="description"
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="Short description"
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
                title="Delete category"
                description={
                    <>
                        Remove “{del.deleting?.name}” from your catalog?
                        Products keep their data but lose this category.
                    </>
                }
            />
        </TenantLayout>
    );
}
