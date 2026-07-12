import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, ClipboardCheck, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { InfoHint } from '@/components/info-hint';
import { SignedQuantity } from '@/components/signed-quantity';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import stockTakesRoutes from '@/routes/tenant/stock-takes';
import type { TenantPageProps } from '@/types';

type StockTake = App.Data.StockTakeData;

type PageProps = TenantPageProps & {
    take: StockTake;
};

export default function StockTakeShow() {
    const { take, tenant } = usePageProps<PageProps>();
    const isDraft = take.status === 'draft';
    const listUrl = stockTakesRoutes.index.url({ tenant: tenant.slug });

    const [counts, setCounts] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            take.items.map((item) => [item.id, String(item.counted_qty)]),
        ),
    );

    const postForm = useForm<{
        items: { id: number; counted_qty: string }[];
    }>({ items: [] });
    const cancelForm = useForm({});

    const variance = (item: StockTake['items'][number]) =>
        (Number(counts[item.id]) || 0) - item.system_qty;

    const totalVariance = take.items.reduce(
        (sum, item) => sum + variance(item),
        0,
    );

    const post = () => {
        postForm.transform(() => ({
            items: take.items.map((item) => ({
                id: item.id,
                counted_qty: counts[item.id] ?? String(item.counted_qty),
            })),
        }));
        postForm.post(
            stockTakesRoutes.post.url({
                tenant: tenant.slug,
                stockTake: take.id,
            }),
            { preserveScroll: true },
        );
    };

    const cancel = () => {
        cancelForm.post(
            stockTakesRoutes.cancel.url({
                tenant: tenant.slug,
                stockTake: take.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Stock takes', href: listUrl },
                {
                    title: `#${take.id}`,
                    href: stockTakesRoutes.show.url({
                        tenant: tenant.slug,
                        stockTake: take.id,
                    }),
                },
            ]}
        >
            <Head title={`Stock take #${take.id}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <h1 className="font-semibold text-2xl tracking-tight">
                            Stock take #{take.id}
                        </h1>
                        <StatusBadge
                            status={take.status}
                            label={take.status_label}
                        />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {take.warehouse ?? '—'}
                        {isDraft
                            ? ' · enter what you physically counted for each item.'
                            : ''}
                    </p>
                </div>
                <Button asChild variant="outline" className="shrink-0">
                    <Link href={listUrl}>
                        <ArrowLeft className="size-4" />
                        Back
                    </Link>
                </Button>
            </div>

            {take.items.length === 0 ? (
                <EmptyState
                    icon={ClipboardCheck}
                    title="Nothing to count"
                    description="This warehouse held no stock when the count started."
                />
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="text-right">
                                        Expected
                                        <InfoHint>
                                            What the system thinks you have.
                                        </InfoHint>
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Counted
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Difference
                                        <InfoHint>
                                            Counted minus expected. Applying the
                                            count corrects your stock by this
                                            amount.
                                        </InfoHint>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {take.items.map((item) => {
                                    const v = variance(item);
                                    return (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <span className="font-medium text-foreground">
                                                    {item.name}
                                                </span>
                                                {item.sku && (
                                                    <span className="ml-2 font-mono text-muted-foreground text-xs">
                                                        {item.sku}
                                                    </span>
                                                )}
                                                <span className="ml-1 text-muted-foreground text-xs">
                                                    {item.unit}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {formatQuantity(
                                                    item.system_qty,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {isDraft ? (
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="any"
                                                        inputMode="decimal"
                                                        aria-label={`Counted quantity for ${item.name}`}
                                                        value={
                                                            counts[item.id] ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setCounts(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [item.id]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className="ml-auto h-8 w-28 text-right tabular-nums"
                                                    />
                                                ) : (
                                                    <span className="tabular-nums">
                                                        {formatQuantity(
                                                            item.counted_qty,
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-medium tabular-nums',
                                                    v < 0 && 'text-destructive',
                                                    v > 0 &&
                                                        'text-emerald-600 dark:text-emerald-400',
                                                    v === 0 &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {v > 0 ? '+' : ''}
                                                {formatQuantity(v)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-muted-foreground text-sm">
                    Total difference:{' '}
                    <SignedQuantity
                        value={totalVariance}
                        className="font-medium"
                    />
                </p>
                {isDraft && (
                    <div className="flex gap-2">
                        <Button
                            variant="ghost"
                            onClick={cancel}
                            disabled={
                                cancelForm.processing || postForm.processing
                            }
                        >
                            <Ban className="size-4" />
                            Cancel take
                        </Button>
                        <Button
                            onClick={post}
                            disabled={
                                postForm.processing || take.items.length === 0
                            }
                        >
                            {postForm.processing ? (
                                <>
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Applying…
                                </>
                            ) : (
                                <>
                                    <ClipboardCheck className="size-4" />
                                    Apply count
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </TenantLayout>
    );
}
