import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type Fact = {
    label: string;
    value: ReactNode;
};

/**
 * A titled card of label/value pairs — the "details" panel of a detail page
 * (party, dates, currency, notes…). Two columns from `sm` up, one on mobile.
 */
export function DetailFacts({
    title = 'Details',
    facts,
}: {
    title?: string;
    facts: Fact[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    {facts.map((fact) => (
                        <div key={fact.label} className="space-y-1">
                            <dt className="text-muted-foreground text-sm">
                                {fact.label}
                            </dt>
                            <dd className="font-medium text-sm">
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}
