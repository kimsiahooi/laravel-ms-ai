import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

/**
 * A titled card that hosts a chart (or any body). When `isEmpty` is true it shows
 * a centered empty message instead of the body, keeping the card the same height
 * so the dashboard grid never jumps. Surfaces are token-based, so it works in
 * light and dark automatically.
 */
export function ChartCard({
    title,
    description,
    action,
    isEmpty = false,
    emptyText = 'No data yet',
    bodyClassName,
    className,
    children,
}: {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    isEmpty?: boolean;
    emptyText?: string;
    bodyClassName?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div className="space-y-1">
                        <CardTitle className="text-base">{title}</CardTitle>
                        {description ? (
                            <CardDescription>{description}</CardDescription>
                        ) : null}
                    </div>
                    {action}
                </div>
            </CardHeader>
            <CardContent className={bodyClassName}>
                {isEmpty ? (
                    <div className="flex h-[220px] items-center justify-center text-center text-muted-foreground text-sm">
                        {emptyText}
                    </div>
                ) : (
                    children
                )}
            </CardContent>
        </Card>
    );
}
