import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, LoaderCircle, PackageX, Printer } from 'lucide-react';
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
import { usePageProps } from '@/hooks/use-page-props';
import PrintLayout from '@/layouts/print-layout';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate, formatQuantity } from '@/lib/format';
import { toOptions } from '@/lib/options';
import { statusVariant } from '@/lib/status';
import { dashboard } from '@/routes/tenant';
import returnsRoutes from '@/routes/tenant/purchase-returns';
import type { TenantPageProps } from '@/types';

type PurchaseReturn = App.Data.PurchaseReturnData;
type Option = App.Data.OptionData;

type PageProps = TenantPageProps & {
    return: PurchaseReturn;
    warehouses: Option[];
    print: boolean;
};

export default function PurchaseReturnShow() {
    const {
        return: ret,
        warehouses,
        print,
        tenant,
    } = usePageProps<PageProps>();
    const showUrl = returnsRoutes.show.url({
        tenant: tenant.slug,
        purchaseReturn: ret.id,
    });

    // Printable variant (?print=1): the letterhead document, unchanged.
    if (print) {
        return (
            <PrintLayout backHref={showUrl}>
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

    return <PurchaseReturnDetail return={ret} warehouses={warehouses} />;
}

function PurchaseReturnDetail({
    return: ret,
    warehouses,
}: {
    return: PurchaseReturn;
    warehouses: Option[];
}) {
    const { tenant } = usePageProps<PageProps>();
    const base = returnsRoutes.index.url({ tenant: tenant.slug });
    const pending = ret.status === 'pending';
    const warehouseOptions = toOptions(warehouses);
    const showUrl = returnsRoutes.show.url({
        tenant: tenant.slug,
        purchaseReturn: ret.id,
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
            returnsRoutes.complete.url({
                tenant: tenant.slug,
                purchaseReturn: ret.id,
            }),
            { preserveScroll: true, onSuccess: () => setCompleteOpen(false) },
        );
    const submitCancel = () =>
        cancelForm.post(
            returnsRoutes.cancel.url({
                tenant: tenant.slug,
                purchaseReturn: ret.id,
            }),
            { preserveScroll: true, onSuccess: () => setCancelOpen(false) },
        );

    const facts: Fact[] = [
        { label: 'Supplier', value: ret.supplier ?? '—' },
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
                      label: 'Completed on',
                      value: (
                          <span suppressHydrationWarning>
                              {formatDate(ret.completed_at)}
                          </span>
                      ),
                  },
              ]
            : []),
        ...(ret.notes ? [{ label: 'Notes', value: ret.notes }] : []),
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Purchase returns', href: base },
                { title: `#${ret.id}`, href: showUrl },
            ]}
        >
            <Head title={`Purchase return #${ret.id}`} />

            <DetailHeader
                title={`Purchase return #${ret.id}`}
                status={{ status: ret.status, label: ret.status_label }}
                description={ret.supplier ? `From ${ret.supplier}` : undefined}
                actions={
                    <>
                        {pending ? (
                            <>
                                <Button onClick={openComplete}>
                                    <PackageX className="size-4" />
                                    Complete return
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

            <Dialog open={completeOpen} onOpenChange={setCompleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete return #{ret.id}</DialogTitle>
                        <DialogDescription>
                            Each line will be removed from your inventory at the
                            selected warehouse.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboboxField
                        id="complete-warehouse"
                        label="Return from"
                        hint="The warehouse the returned stock leaves from."
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
                                'Complete return'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel purchase return</DialogTitle>
                        <DialogDescription>
                            Cancel return #{ret.id}? It can no longer be
                            completed or edited.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                variant="ghost"
                                disabled={cancelForm.processing}
                            >
                                Keep return
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            <Ban className="size-4" />
                            Cancel return
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
