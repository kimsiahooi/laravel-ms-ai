import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, Factory, LoaderCircle, Printer } from 'lucide-react';
import { useState } from 'react';
import { ComboboxField } from '@/components/combobox-field';
import { DetailFacts, type Fact } from '@/components/detail-facts';
import { DetailHeader } from '@/components/detail-header';
import { LineItemsTable } from '@/components/line-items-table';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
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
import { productionOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate, formatQuantity } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { statusVariant } from '@/lib/status';
import { dashboard } from '@/routes/tenant';
import productionRoutes from '@/routes/tenant/production-orders';
import type { TenantPageProps } from '@/types';

type ProductionOrder = App.Data.ProductionOrderData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    order: ProductionOrder;
    warehouses: Option[];
    print: boolean;
};

export default function ProductionOrderShow() {
    const { order, warehouses, print, tenant } = usePageProps<PageProps>();
    const showUrl = productionRoutes.show.url({
        tenant: tenant.slug,
        productionOrder: order.id,
    });

    // Printable variant (?print=1): the letterhead document, unchanged.
    if (print) {
        return (
            <PrintLayout backHref={showUrl}>
                <Head title={`Work Order #${order.id}`} />

                <PrintDocument
                    org={tenant.name}
                    docType="Production Order"
                    number={`Work Order #${order.id}`}
                    statusLabel={order.status_label}
                    statusVariant={statusVariant(order.status)}
                    party={{
                        heading: 'Product to build',
                        name: order.product,
                        detail: `Build quantity: ${formatQuantity(order.quantity)}`,
                    }}
                    meta={[
                        {
                            label: 'Order date',
                            value: (
                                <span suppressHydrationWarning>
                                    {formatDate(order.created_at)}
                                </span>
                            ),
                        },
                        ...(order.completed_at
                            ? [
                                  {
                                      label: 'Completed',
                                      value: (
                                          <span suppressHydrationWarning>
                                              {formatDate(order.completed_at)}
                                          </span>
                                      ),
                                  },
                              ]
                            : []),
                        {
                            label: 'Build quantity',
                            value: formatQuantity(order.quantity),
                        },
                    ]}
                >
                    <PrintItemsTable
                        head={['Raw material', 'Per unit', 'Required']}
                        rows={order.items.map((item) => ({
                            key: item.id,
                            cells: [
                                item.name,
                                formatQuantity(item.quantity_per_unit),
                                formatQuantity(item.quantity_required),
                            ],
                        }))}
                    />
                </PrintDocument>
            </PrintLayout>
        );
    }

    return <ProductionOrderDetail order={order} warehouses={warehouses} />;
}

function ProductionOrderDetail({
    order,
    warehouses,
}: {
    order: ProductionOrder;
    warehouses: Option[];
}) {
    const { tenant } = usePageProps<PageProps>();
    const base = productionRoutes.index.url({ tenant: tenant.slug });
    const pending = order.status === 'pending';
    const warehouseOptions = toOptions(warehouses);
    const showUrl = productionRoutes.show.url({
        tenant: tenant.slug,
        productionOrder: order.id,
    });
    const printUrl = `${showUrl}?print=1`;

    const [completeOpen, setCompleteOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const completeForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const openComplete = () => {
        completeForm.reset();
        completeForm.clearErrors();
        setCompleteOpen(true);
    };
    const submitComplete = () =>
        completeForm.post(
            productionRoutes.complete.url({
                tenant: tenant.slug,
                productionOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setCompleteOpen(false) },
        );
    const submitCancel = () =>
        cancelForm.post(
            productionRoutes.cancel.url({
                tenant: tenant.slug,
                productionOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelOpen(false) },
        );

    const facts: Fact[] = [
        { label: 'Product', value: order.product },
        { label: 'Build quantity', value: formatQuantity(order.quantity) },
        {
            label: 'Order date',
            value: (
                <span suppressHydrationWarning>
                    {formatDate(order.created_at)}
                </span>
            ),
        },
        ...(order.completed_at
            ? [
                  {
                      label: 'Completed on',
                      value: (
                          <span suppressHydrationWarning>
                              {formatDate(order.completed_at)}
                          </span>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: productionOrderMeta.plural, href: base },
                { title: `#${order.id}`, href: showUrl },
            ]}
        >
            <Head title={`Production order #${order.id}`} />

            <DetailHeader
                title={`Production order #${order.id}`}
                status={{ status: order.status, label: order.status_label }}
                description={`${formatQuantity(order.quantity)} × ${order.product}`}
                actions={
                    <>
                        {pending ? (
                            <>
                                <Button onClick={openComplete}>
                                    <Factory className="size-4" />
                                    Complete build
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setCancelOpen(true)}
                                >
                                    <Ban className="size-4" />
                                    Cancel
                                </Button>
                            </>
                        ) : null}
                        <Button variant="outline" asChild>
                            <Link href={printUrl}>
                                <Printer className="size-4" />
                                Print
                            </Link>
                        </Button>
                    </>
                }
            />

            <DetailFacts facts={facts} />

            <LineItemsTable
                head={['Raw material', 'Per unit', 'Required']}
                rows={order.items.map((item) => ({
                    key: item.id,
                    cells: [
                        item.name,
                        formatQuantity(item.quantity_per_unit),
                        formatQuantity(item.quantity_required),
                    ],
                }))}
            />

            <Dialog open={completeOpen} onOpenChange={setCompleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Complete production order #{order.id}
                        </DialogTitle>
                        <DialogDescription>
                            This will consume the materials and add the finished
                            “{order.product}” at a warehouse. If any material is
                            short there, nothing happens.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="complete-warehouse"
                        label="Warehouse"
                        hint="Where the finished units are added and the materials are consumed."
                        options={warehouseOptions}
                        value={completeForm.data.warehouse_id}
                        onChange={(value) =>
                            completeForm.setData('warehouse_id', value)
                        }
                        error={completeForm.errors.warehouse_id}
                        placeholder="Select warehouse"
                        searchPlaceholder="Search warehouses…"
                        emptyText="No warehouses found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={completeForm.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitComplete}
                            disabled={
                                completeForm.processing ||
                                !completeForm.data.warehouse_id
                            }
                        >
                            {completeForm.processing ? (
                                <>
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Completing…
                                </>
                            ) : (
                                <>
                                    <Factory className="size-4" />
                                    Complete build
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel production order</DialogTitle>
                        <DialogDescription>
                            Cancel order #{order.id}? It can no longer be
                            completed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={cancelForm.processing}
                            >
                                Keep order
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            <Ban className="size-4" />
                            Cancel order
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
