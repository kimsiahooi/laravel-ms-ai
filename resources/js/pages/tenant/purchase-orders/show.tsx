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
import { purchaseOrderMeta } from '@/config/resources';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate, formatMoney, formatQuantity } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { statusVariant } from '@/lib/status';
import { dashboard } from '@/routes/tenant';
import poRoutes from '@/routes/tenant/purchase-orders';
import type { TenantPageProps } from '@/types';

type PurchaseOrder = App.Data.PurchaseOrderData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    order: PurchaseOrder;
    warehouses: Option[];
    print: boolean;
};

export default function PurchaseOrderShow() {
    const { order, warehouses, print, tenant } = usePageProps<PageProps>();
    const currency = order.currency;
    const showUrl = poRoutes.show.url({
        tenant: tenant.slug,
        purchaseOrder: order.id,
    });

    // Printable variant (?print=1): the letterhead document, unchanged.
    if (print) {
        return (
            <PrintLayout backHref={showUrl}>
                <Head title={`PO #${order.id}`} />

                <PrintDocument
                    org={tenant.name}
                    docType="Purchase Order"
                    number={`PO #${order.id}`}
                    statusLabel={order.status_label}
                    statusVariant={statusVariant(order.status)}
                    party={{ heading: 'Supplier', name: order.supplier ?? '—' }}
                    meta={[
                        {
                            label: 'Order date',
                            value: (
                                <span suppressHydrationWarning>
                                    {formatDate(order.created_at)}
                                </span>
                            ),
                        },
                        ...(order.received_at
                            ? [
                                  {
                                      label: 'Received date',
                                      value: (
                                          <span suppressHydrationWarning>
                                              {formatDate(order.received_at)}
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
                            'Raw material',
                            'Quantity',
                            'Unit cost',
                            'Line total',
                        ]}
                        rows={order.items.map((item) => ({
                            key: item.id,
                            cells: [
                                item.name,
                                formatQuantity(item.quantity),
                                formatMoney(item.unit_cost, currency),
                                formatMoney(
                                    item.quantity * item.unit_cost,
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

    return <PurchaseOrderDetail order={order} warehouses={warehouses} />;
}

function PurchaseOrderDetail({
    order,
    warehouses,
}: {
    order: PurchaseOrder;
    warehouses: Option[];
}) {
    const { tenant } = usePageProps<PageProps>();
    const currency = order.currency;
    const base = poRoutes.index.url({ tenant: tenant.slug });
    const pending = order.status === 'pending';
    const warehouseOptions = toOptions(warehouses);
    const showUrl = poRoutes.show.url({
        tenant: tenant.slug,
        purchaseOrder: order.id,
    });
    const printUrl = `${showUrl}?print=1`;

    const [receiveOpen, setReceiveOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const receiveForm = useForm({ warehouse_id: '' });
    const cancelForm = useForm({});

    const openReceive = () => {
        receiveForm.reset();
        receiveForm.clearErrors();
        setReceiveOpen(true);
    };
    const submitReceive = () =>
        receiveForm.post(
            poRoutes.receive.url({
                tenant: tenant.slug,
                purchaseOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setReceiveOpen(false) },
        );
    const submitCancel = () =>
        cancelForm.post(
            poRoutes.cancel.url({
                tenant: tenant.slug,
                purchaseOrder: order.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelOpen(false) },
        );

    const facts: Fact[] = [
        { label: 'Supplier', value: order.supplier ?? '—' },
        {
            label: 'Order date',
            value: (
                <span suppressHydrationWarning>
                    {formatDate(order.created_at)}
                </span>
            ),
        },
        ...(order.received_at
            ? [
                  {
                      label: 'Received on',
                      value: (
                          <span suppressHydrationWarning>
                              {formatDate(order.received_at)}
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
                { title: purchaseOrderMeta.plural, href: base },
                { title: `#${order.id}`, href: showUrl },
            ]}
        >
            <Head title={`Purchase order #${order.id}`} />

            <DetailHeader
                title={`Purchase order #${order.id}`}
                status={{ status: order.status, label: order.status_label }}
                description={
                    order.supplier ? `From ${order.supplier}` : undefined
                }
                actions={
                    <>
                        {pending ? (
                            <>
                                <Button onClick={openReceive}>
                                    <PackageCheck className="size-4" />
                                    Receive
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
                head={['Raw material', 'Quantity', 'Unit cost', 'Line total']}
                rows={order.items.map((item) => ({
                    key: item.id,
                    cells: [
                        item.name,
                        formatQuantity(item.quantity),
                        formatMoney(item.unit_cost, currency),
                        formatMoney(item.quantity * item.unit_cost, currency),
                    ],
                }))}
                total={{
                    label: 'Total',
                    value: formatMoney(order.total, currency),
                }}
            />

            <Dialog open={receiveOpen} onOpenChange={setReceiveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Receive purchase order #{order.id}
                        </DialogTitle>
                        <DialogDescription>
                            Each line will be added to your inventory at the
                            selected warehouse.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="receive-warehouse"
                        label="Receive into"
                        hint="The warehouse where received stock will be added."
                        options={warehouseOptions}
                        value={receiveForm.data.warehouse_id}
                        onChange={(value) =>
                            receiveForm.setData('warehouse_id', value)
                        }
                        error={receiveForm.errors.warehouse_id}
                        placeholder="Select warehouse"
                        searchPlaceholder="Search warehouses…"
                        emptyText="No warehouses found."
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={receiveForm.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={submitReceive}
                            disabled={
                                receiveForm.processing ||
                                !receiveForm.data.warehouse_id
                            }
                        >
                            {receiveForm.processing ? (
                                <>
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Receiving…
                                </>
                            ) : (
                                'Receive'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel purchase order</DialogTitle>
                        <DialogDescription>
                            Cancel order #{order.id}? It can no longer be
                            received or edited.
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
