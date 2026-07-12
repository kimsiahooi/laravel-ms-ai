import { Head } from '@inertiajs/react';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import { formatDate, formatMoney, formatQuantity } from '@/lib/format';
import { statusVariant } from '@/lib/status';
import poRoutes from '@/routes/tenant/purchase-orders';
import type { TenantPageProps } from '@/types';

type PurchaseOrder = App.Data.PurchaseOrderData;

type PageProps = TenantPageProps & { order: PurchaseOrder };

export default function PurchaseOrderShow() {
    const { order, tenant } = usePageProps<PageProps>();
    const currency = order.currency;

    return (
        <PrintLayout backHref={poRoutes.index.url({ tenant: tenant.slug })}>
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
                                  label: 'Received',
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
