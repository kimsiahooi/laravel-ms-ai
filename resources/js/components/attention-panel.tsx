import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import type { ComponentType } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type AttentionItem = {
    /** Stable React key for the row. */
    key: string;
    icon: ComponentType<{ className?: string }>;
    /** What is waiting, in the reader's words: "2 items have run out". */
    text: string;
    /** The verb on the link: "Reorder", "Chase", "Fulfil". */
    action: string;
    href: string;
    /** `critical` = money or production is stopped right now. */
    tone?: 'critical' | 'default';
};

/**
 * The short list of things actually waiting on someone today, each linking
 * straight to the list where it gets dealt with. Shows a done state rather than
 * an empty box when there is nothing outstanding.
 */
export function AttentionPanel({
    items,
    emptyText,
    className,
}: {
    items: AttentionItem[];
    /** Overrides the done-state line when "all caught up" wouldn't be true yet. */
    emptyText?: string;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="text-base">
                    Needs your attention
                </CardTitle>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                        <CheckCircle2 className="size-8 text-muted-foreground" />
                        <p className="font-medium text-sm">
                            {emptyText
                                ? 'Nothing here yet'
                                : "You're all caught up"}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {emptyText ??
                                'Nothing is waiting on you right now.'}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {items.map((item) => (
                            <li
                                key={item.key}
                                className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="flex min-w-0 items-start gap-2.5">
                                    <item.icon
                                        className={cn(
                                            'mt-0.5 size-4 shrink-0',
                                            item.tone === 'critical'
                                                ? 'text-destructive-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                        aria-hidden="true"
                                    />
                                    {/* Wraps rather than truncates — the reason is
                                        the part worth reading. */}
                                    <span className="text-sm leading-snug">
                                        {item.text}
                                    </span>
                                </span>
                                <Link
                                    href={item.href}
                                    className="shrink-0 font-medium text-primary text-sm hover:underline"
                                >
                                    {item.action}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
