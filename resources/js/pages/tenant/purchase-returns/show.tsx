import { Head } from '@inertiajs/react';
import { PrintDocument, PrintItemsTable } from '@/components/print-document';
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import { formatDate, formatQuantity } from '@/lib/format';
import returnsRoutes from '@/routes/tenant/purchase-returns';
import type { TenantPageProps } from '@/types';

type PurchaseReturn = App.Data.PurchaseReturnData;

type PageProps = TenantPageProps & { return: PurchaseReturn };

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}

export default function PurchaseReturnShow() {
    const { return: ret, tenant } = usePageProps<PageProps>();

    return (
        <PrintLayout
            backHref={returnsRoutes.index.url({ tenant: tenant.slug })}
        >
            <Head title={`Purchase return #${ret.id}`} />

            <PrintDocument
                org={tenant.name}
                docType="Purchase Return"
                number={`Return #${ret.id}`}
                statusLabel={ret.status_label}
                statusVariant={statusVariant(ret.status)}
                party={{ heading: 'Supplier', name: ret.supplier ?? '—' }}
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
                    head={['Raw material', 'Quantity']}
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
