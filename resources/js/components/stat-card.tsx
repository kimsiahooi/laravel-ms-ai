import type { ComponentType, ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * A single KPI tile: label, a large tabular value, an optional sub-line, and a
 * neutral (not brand-indigo) icon badge. Shared by the admin + tenant dashboards
 * so every stat reads identically in light and dark.
 */
export function StatCard({
    icon: Icon,
    label,
    value,
    sub,
    valueClassName,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
    sub?: ReactNode;
    valueClassName?: string;
}) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p
                        className={cn(
                            'font-semibold text-2xl tabular-nums',
                            valueClassName,
                        )}
                    >
                        {value}
                    </p>
                    {sub != null ? (
                        <p className="truncate text-muted-foreground text-xs">
                            {sub}
                        </p>
                    ) : null}
                </div>
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-foreground">
                    <Icon className="size-4" />
                </span>
            </div>
        </Card>
    );
}
