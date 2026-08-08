import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * A single KPI tile: label, a large tabular value, an optional sub-line, and a
 * neutral (not brand-indigo) icon badge. Shared by the admin + tenant dashboards
 * so every stat reads identically in light and dark.
 *
 * Pass `href` to make the whole tile a link through to the list it summarises, and
 * `tone="critical"` to give the one tile that needs acting on a red accent.
 */
export function StatCard({
    icon: Icon,
    label,
    value,
    sub,
    valueClassName,
    href,
    tone = 'default',
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
    sub?: ReactNode;
    valueClassName?: string;
    /** Makes the tile a link to the underlying list. */
    href?: string;
    /** `critical` = needs attention now (red accent). */
    tone?: 'default' | 'critical';
}) {
    const critical = tone === 'critical';

    const card = (
        <Card
            className={cn(
                'h-full p-5 transition-colors',
                critical && 'border-destructive/40 bg-destructive/5',
                // Hover keeps a critical tile red — going neutral would read as
                // "resolved". Only a default tile picks up the accent surface.
                href &&
                    !critical &&
                    'group-hover:border-primary/40 group-hover:bg-accent/40',
                href &&
                    critical &&
                    'group-hover:border-destructive/60 group-hover:bg-destructive/10',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p
                        className={cn(
                            'flex items-center gap-1 text-muted-foreground text-sm',
                            critical && 'text-destructive-foreground',
                        )}
                    >
                        {label}
                        {/* Always visible, so a touch user can see the tile leads
                            somewhere; it also reserves no extra vertical space. */}
                        {href ? (
                            <ArrowRight
                                className="size-3 opacity-60"
                                aria-hidden="true"
                            />
                        ) : null}
                    </p>
                    <p
                        className={cn(
                            'font-semibold text-2xl tabular-nums',
                            critical && 'text-destructive-foreground',
                            valueClassName,
                        )}
                    >
                        {value}
                    </p>
                    {sub != null ? (
                        <p
                            className={cn(
                                'truncate text-muted-foreground text-xs',
                                // Full strength: this is the line asking for action,
                                // so it must not be the faintest text on the card.
                                critical && 'text-destructive-foreground',
                            )}
                        >
                            {sub}
                        </p>
                    ) : null}
                </div>
                <span
                    className={cn(
                        'grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-foreground',
                        critical &&
                            'bg-destructive/10 text-destructive-foreground',
                    )}
                >
                    <Icon className="size-4" />
                </span>
            </div>
        </Card>
    );

    if (!href) return card;

    return (
        <Link
            href={href}
            className="group block rounded-xl focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            {card}
        </Link>
    );
}
