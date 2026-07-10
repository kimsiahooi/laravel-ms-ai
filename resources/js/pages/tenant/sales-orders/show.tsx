import { Head } from '@inertiajs/react';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import { formatDate, formatMoney, formatQuantity } from '@/lib/format';
import soRoutes from '@/routes/tenant/sales-orders';
import type { TenantPageProps } from '@/types';

type SalesOrder = App.Data.SalesOrderData;

type PageProps = TenantPageProps & { order: SalesOrder };

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'fulfilled') return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}

export default function SalesOrderShow() {
    const { order, tenant } = usePageProps<PageProps>();
    const currency = order.currency;

    return (
        <PrintLayout backHref={soRoutes.index.url({ tenant: tenant.slug })}>
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
                        value: formatDate(order.created_at),
                    },
                    ...(order.fulfilled_at
                        ? [
                              {
                                  label: 'Fulfilled',
                                  value: formatDate(order.fulfilled_at),
                              },
                          ]
                        : []),
                    { label: 'Currency', value: currency },
                ]}
            >
                <PrintItemsTable
                    head={['Product', 'Qty', 'Unit price', 'Line total']}
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
