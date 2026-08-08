import { CircleAlert, CircleCheck, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Readiness = App.Data.EInvoiceReadinessData;

/**
 * The e-invoice readiness checklist for a sales order: which tax-identity fields
 * are present and which are still blank, plus the JSON download. The download is
 * never blocked — a not-ready payload is still useful (the gaps are visible in
 * it), so the panel warns rather than disables.
 */
export function EInvoicePanel({
    readiness,
    downloadUrl,
    standardLabel,
}: {
    readiness: Readiness;
    downloadUrl: string;
    /** e.g. "MyInvois" or "InvoiceNow" — what the payload is shaped for. */
    standardLabel: string;
}) {
    const { ready, passed, total, checks } = readiness;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div className="space-y-1">
                    <CardTitle>E-invoice</CardTitle>
                    <p className="text-muted-foreground text-sm">
                        {ready
                            ? `Ready — this order has everything ${standardLabel} needs.`
                            : `${passed} of ${total} checks pass. Fill the gaps below before submitting to ${standardLabel}.`}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <a href={downloadUrl} download>
                        <Download className="size-4" />
                        Download JSON
                    </a>
                </Button>
            </CardHeader>
            <CardContent>
                <ul className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    {checks.map((check) => (
                        <li key={check.key} className="flex items-start gap-2">
                            {check.passed ? (
                                <CircleCheck
                                    className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                    aria-hidden="true"
                                />
                            ) : (
                                <CircleAlert
                                    className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                    aria-hidden="true"
                                />
                            )}
                            <div className="min-w-0 space-y-0.5">
                                <p className="font-medium text-sm">
                                    {check.label}
                                    <span className="sr-only">
                                        {check.passed
                                            ? ' — present'
                                            : ' — missing'}
                                    </span>
                                </p>
                                {check.passed ? null : (
                                    <p className="text-muted-foreground text-sm">
                                        {check.hint}
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
