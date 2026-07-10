import type { ComponentType, ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

type EmptyStateProps = {
    /** Lucide icon (or any `{ className }` component) shown in the badge. */
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: ReactNode;
    /** Optional call-to-action, e.g. a "New …" button. */
    action?: ReactNode;
};

/**
 * The shared "no rows yet" card used by every `DataTable` empty state: a
 * centered icon badge, title, description, and an optional action button.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: EmptyStateProps) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                    <Icon className="size-6" />
                </span>
                <div className="space-y-1">
                    <h3 className="font-semibold text-lg">{title}</h3>
                    <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                        {description}
                    </p>
                </div>
                {action}
            </CardContent>
        </Card>
    );
}
