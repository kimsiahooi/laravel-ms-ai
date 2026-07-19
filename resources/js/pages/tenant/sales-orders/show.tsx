import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, LoaderCircle, PackageCheck, Printer } from 'lucide-react';
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
import { salesOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate, formatMoney, formatQuantity } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { statusVariant } from '@/lib/status';
import { dashboard } from '@/routes/tenant';
import soRoutes from '@/routes/tenant/sales-orders';
import type { TenantPageProps } from '@/types';

type SalesOrder = App.Data.SalesOrderData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    order: SalesOrder;
    warehouses: Option[];
    print: boolean;
};

export default function SalesOrderShow() {
    const { order, warehouses, print, tenant } = usePageProps<PageProps>();
    const currency = order.currency;
    const showUrl = soRoutes.show.url({
        tenant: tenant.slug,
        salesOrder: order.id,
    });

    // Printable variant (?print=1): the letterhead document, unchanged.
    if (print) {
        return (
            <PrintLayout backHref={showUrl}>
                <Head title={`SO #${order.id}`} />

                <PrintDocument
                    org={tenant.name}
                    docType="Sales Order"
                    number={`SO #${order.id}`}
                    statusLabel={order.status_label}
                    statusVariant={statusVariant(order.status)}
                    party={{ heading: 'Customer', name: order.customer ?? '—' }}
                    meta={[
                        {
                            label: 'Order date',
                            value: (
                                <span suppressHydrationWarning>
                                    {formatDate(order.created_at)}
                                </span>
                            ),
                        },
                        ...(order.fulfilled_at
                            ? [
                                  {
                                      label: 'Fulfilled date',
                                      value: (
                                          <span suppressHydrationWarning>
                                              {formatDate(order.fulfilled_at)}
                                          </span>
                                      ),
                                  },
                              ]
                            : []),
                        { label: 'Currency', value: currency },
                    ]}
                >
                    <PrintItemsTable
                        head={[
                            'Product',
                            'Quantity',
                            'Unit price',
                            'Line total',
                        ]}
                        rows={order.items.map((item) => ({
                            key: item.id,
                            cells: [
                                item.name,
                                formatQuantity(item.quantity),
                                formatMoney(item.unit_price, currency),
                                formatMoney(
                                    item.quantity * item.unit_price,
                                    currency,
                                ),
                            ],
                        }))}
                        total={{
                            label: 'Total',
                            value: formatMoney(order.total, currency),
                        }}
                    />
                </PrintDocument>
            </PrintLayout>
        );
    }

    return <SalesOrderDetail order={order} warehouses={warehouses} />;
}

function SalesOrderDetail({
    order,
    warehouses,
}: {
    order: SalesOrder;
    warehouses: Option[];
}) {
    const { tenant } = usePageProps<PageProps>();
    const currency = order.currency;
    const base = soRoutes.index.url({ tenant: tenant.slug });
    const pending = order.status === 'pending';
    const warehouseOptions = toOptions(warehouses);
    const showUrl = soRoutes.show.url({
        tenant: tenant.slug,
        salesOrder: order.id,
    });
    const printUrl = `${showUrl}?print=1`;

    const [fulfillOpen, setFulfillOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const fulfillForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const openFulfill = () => {
        fulfillForm.reset();
        fulfillForm.clearErrors();
        setFulfillOpen(true);
    };
    const submitFulfill = () =>
        fulfillForm.post(
            soRoutes.fulfill.url({
                tenant: tenant.slug,
                salesOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setFulfillOpen(false) },
        );
    const submitCancel = () =>
        cancelForm.post(
            soRoutes.cancel.url({
                tenant: tenant.slug,
                salesOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelOpen(false) },
        );

    const facts: Fact[] = [
        { label: 'Customer', value: order.customer ?? '—' },
        {
            label: 'Order date',
            value: (
                <span suppressHydrationWarning>
                    {formatDate(order.created_at)}
                </span>
            ),
        },
        ...(order.fulfilled_at
            ? [
                  {
                      label: 'Fulfilled on',
                      value: (
                          <span suppressHydrationWarning>
                              {formatDate(order.fulfilled_at)}
                          </span>
                      ),
                  },
              ]
            : []),
        { label: 'Currency', value: currency },
        ...(order.notes ? [{ label: 'Notes', value: order.notes }] : []),
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: salesOrderMeta.plural, href: base },
                { title: `#${order.id}`, href: showUrl },
            ]}
        >
            <Head title={`Sales order #${order.id}`} />

            <DetailHeader
                title={`Sales order #${order.id}`}
                status={{ status: order.status, label: order.status_label }}
                description={
                    order.customer ? `For ${order.customer}` : undefined
                }
                actions={
                    <>
                        {pending ? (
                            <>
                                <Button onClick={openFulfill}>
                                    <PackageCheck className="size-4" />
                                    Fulfill
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
                head={['Product', 'Quantity', 'Unit price', 'Line total']}
                rows={order.items.map((item) => ({
                    key: item.id,
                    cells: [
                        item.name,
                        formatQuantity(item.quantity),
                        formatMoney(item.unit_price, currency),
                        formatMoney(item.quantity * item.unit_price, currency),
                    ],
                }))}
                total={{
                    label: 'Total',
                    value: formatMoney(order.total, currency),
                }}
            />

            <Dialog open={fulfillOpen} onOpenChange={setFulfillOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Fulfill sales order #{order.id}
                        </DialogTitle>
                        <DialogDescription>
                            Choose the warehouse to ship this order's items
                            from.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="fulfill-warehouse"
                        label="Fulfill from"
                        hint="Stock will be deducted from this warehouse."
                        options={warehouseOptions}
                        value={fulfillForm.data.warehouse_id}
                        onChange={(value) =>
                            fulfillForm.setData('warehouse_id', value)
                        }
                        error={fulfillForm.errors.warehouse_id}
                        placeholder="Select warehouse"
                        searchPlaceholder="Search warehouses…"
                        emptyText="No warehouses found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={fulfillForm.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitFulfill}
                            disabled={
                                fulfillForm.processing ||
                                !fulfillForm.data.warehouse_id
                            }
                        >
                            {fulfillForm.processing ? (
                                <>
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Fulfilling…
                                </>
                            ) : (
                                'Fulfill'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel sales order</DialogTitle>
                        <DialogDescription>
                            Cancel order #{order.id}? It can no longer be
                            fulfilled or edited.
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
