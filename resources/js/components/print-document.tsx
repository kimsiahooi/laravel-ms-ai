import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type MetaRow = { label: string; value: ReactNode };

/**
 * The line-items table shared by every order document. Column 0 is left-aligned
 * text; every other column is right-aligned tabular numbers. An optional totals
 * row spans all but the last column.
 */
export function PrintItemsTable({
    head,
    rows,
    total,
}: {
    head: string[];
    rows: { key: string | number; cells: ReactNode[] }[];
    total?: { label: string; value: ReactNode };
}) {
    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="border-b text-muted-foreground print:text-black">
                    {head.map((label, index) => (
                        <th
                            key={label}
                            className={cn(
                                'py-2 font-medium',
                                index === 0 ? 'text-left' : 'text-right',
                            )}
                        >
                            {label}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.length === 0 ? (
                    <tr>
                        <td
                            colSpan={head.length}
                            className="py-6 text-center text-muted-foreground print:text-black"
                        >
                            No line items.
                        </td>
                    </tr>
                ) : (
                    rows.map((row) => (
                        <tr key={row.key} className="border-border/60 border-b">
                            {head.map((label, index) => (
                                <td
                                    key={`${row.key}:${label}`}
                                    className={cn(
                                        'py-2 align-top',
                                        index === 0
                                            ? 'text-left'
                                            : 'text-right tabular-nums',
                                    )}
                                >
                                    {row.cells[index]}
                                </td>
                            ))}
                        </tr>
                    ))
                )}
            </tbody>
            {total ? (
                <tfoot>
                    <tr>
                        <td
                            colSpan={head.length - 1}
                            className="py-3 text-right font-medium"
                        >
                            {total.label}
                        </td>
                        <td className="py-3 text-right font-semibold tabular-nums">
                            {total.value}
                        </td>
                    </tr>
                </tfoot>
            ) : null}
        </table>
    );
}

/**
 * The printable sheet shared by every order document (PO / SO / MO): an org
 * header with the document type + number + status, a two-column meta/party block,
 * then the caller's line-items table + totals as children. On paper it renders as
 * black ink on white (`print:*`) regardless of the app theme.
 */
export function PrintDocument({
    org,
    docType,
    number,
    statusLabel,
    statusVariant,
    party,
    meta,
    children,
}: {
    org: string;
    docType: string;
    number: string;
    statusLabel: string;
    statusVariant: 'default' | 'secondary' | 'outline';
    party: { heading: string; name: string; detail?: string };
    meta: MetaRow[];
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
            <header className="flex items-start justify-between gap-4 border-b pb-6">
                <div className="min-w-0">
                    <p className="truncate font-semibold text-lg">{org}</p>
                    <p className="text-muted-foreground text-sm print:text-black">
                        {docType}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <p className="font-semibold text-xl tabular-nums">
                        {number}
                    </p>
                    <Badge variant={statusVariant} className="print:hidden">
                        {statusLabel}
                    </Badge>
                    <span className="hidden text-muted-foreground text-sm print:inline print:text-black">
                        {statusLabel}
                    </span>
                </div>
            </header>

            <section className="grid gap-6 py-6 sm:grid-cols-2">
                <div>
                    <p className="mb-1 font-medium text-muted-foreground text-xs uppercase tracking-wide print:text-black">
                        {party.heading}
                    </p>
                    <p className="font-medium">{party.name}</p>
                    {party.detail ? (
                        <p className="text-muted-foreground text-sm print:text-black">
                            {party.detail}
                        </p>
                    ) : null}
                </div>
                <dl className="space-y-1 sm:text-right">
                    {meta.map((row) => (
                        <div
                            key={row.label}
                            className="flex justify-between gap-4 sm:justify-end"
                        >
                            <dt className="text-muted-foreground text-sm print:text-black">
                                {row.label}
                            </dt>
                            <dd className="text-sm tabular-nums">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            {children}
        </div>
    );
}
