import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type LineItemRow = {
    key: string | number;
    /** Cells indexed positionally against `head`; col 0 is left-aligned. */
    cells: ReactNode[];
};

/**
 * The read-only line-items table on a detail page — the on-screen counterpart to
 * PrintItemsTable, built from the vendored `ui/table` primitives inside a titled
 * card. Column 0 is left-aligned; the rest are right-aligned tabular figures.
 */
export function LineItemsTable({
    title = 'Line items',
    head,
    rows,
    summary,
    total,
    emptyText = 'No line items.',
}: {
    title?: string;
    head: string[];
    rows: LineItemRow[];
    /** Optional pre-total footer rows (e.g. subtotal + tax), less emphasised. */
    summary?: { key: string; label: ReactNode; value: ReactNode }[];
    total?: { label: string; value: ReactNode };
    emptyText?: string;
}) {
    const align = (index: number) =>
        index === 0 ? 'text-left' : 'text-right tabular-nums';

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {head.map((label, index) => (
                                    <TableHead
                                        key={label}
                                        className={align(index)}
                                    >
                                        {label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={head.length}
                                        className="text-center text-muted-foreground"
                                    >
                                        {emptyText}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rows.map((row) => (
                                    <TableRow key={row.key}>
                                        {row.cells.map((cell, index) => (
                                            <TableCell
                                                // biome-ignore lint/suspicious/noArrayIndexKey: cells are a fixed positional tuple
                                                key={index}
                                                className={cn(
                                                    align(index),
                                                    index === 0 &&
                                                        'font-medium',
                                                )}
                                            >
                                                {cell}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                        {summary?.length || total ? (
                            <TableFooter>
                                {summary?.map((row) => (
                                    <TableRow key={row.key}>
                                        <TableCell
                                            colSpan={head.length - 1}
                                            className="text-right font-normal text-muted-foreground"
                                        >
                                            {row.label}
                                        </TableCell>
                                        <TableCell className="text-right font-normal tabular-nums">
                                            {row.value}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {total ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={head.length - 1}
                                            className="text-right font-medium"
                                        >
                                            {total.label}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {total.value}
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableFooter>
                        ) : null}
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}
