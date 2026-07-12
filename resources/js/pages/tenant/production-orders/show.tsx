import { Head } from '@inertiajs/react';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import { formatDate, formatQuantity } from '@/lib/format';
import { statusVariant } from '@/lib/status';
import productionRoutes from '@/routes/tenant/production-orders';
import type { TenantPageProps } from '@/types';

type ProductionOrder = App.Data.ProductionOrderData;

type PageProps = TenantPageProps & { order: ProductionOrder };

export default function ProductionOrderShow() {
    const { order, tenant } = usePageProps<PageProps>();

    return (
        <PrintLayout
            backHref={productionRoutes.index.url({ tenant: tenant.slug })}
        >
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
