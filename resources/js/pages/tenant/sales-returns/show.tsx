import { Head } from '@inertiajs/react';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import { formatDate, formatQuantity } from '@/lib/format';
import { statusVariant } from '@/lib/status';
import returnsRoutes from '@/routes/tenant/sales-returns';
import type { TenantPageProps } from '@/types';

type SalesReturn = App.Data.SalesReturnData;

type PageProps = TenantPageProps & { return: SalesReturn };

export default function SalesReturnShow() {
    const { return: ret, tenant } = usePageProps<PageProps>();

    return (
        <PrintLayout
            backHref={returnsRoutes.index.url({ tenant: tenant.slug })}
        >
            <Head title={`Sales return #${ret.id}`} />

            <PrintDocument
                org={tenant.name}
                docType="Sales Return"
                number={`Return #${ret.id}`}
                statusLabel={ret.status_label}
                statusVariant={statusVariant(ret.status)}
                party={{ heading: 'Customer', name: ret.customer ?? '—' }}
                meta={[
                    {
                        label: 'Created',
                        value: (
                            <span suppressHydrationWarning>
                                {formatDate(ret.created_at)}
                            </span>
                        ),
                    },
                    ...(ret.completed_at
                        ? [
                              {
                                  label: 'Completed',
                                  value: (
                                      <span suppressHydrationWarning>
                                          {formatDate(ret.completed_at)}
                                      </span>
                                  ),
                              },
                          ]
                        : []),
                ]}
            >
                <PrintItemsTable
                    head={['Product', 'Quantity']}
                    rows={ret.items.map((item) => ({
                        key: item.id,
                        cells: [item.name, formatQuantity(item.quantity)],
                    }))}
                    total={{
                        label: 'Total quantity',
                        value: formatQuantity(ret.total_quantity),
                    }}
                />
            </PrintDocument>
        </PrintLayout>
    );
}
