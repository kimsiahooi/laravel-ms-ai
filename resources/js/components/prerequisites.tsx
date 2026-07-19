import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ComponentType } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';

export type Prerequisite = {
    /** Lower-case noun phrase, e.g. "a supplier" — reads as "Add a supplier". */
    label: string;
    /** Where the user goes to satisfy it. */
    href: string;
    /** Whether it's already satisfied. */
    met: boolean;
};

/** The still-unmet prerequisites, in order. */
export function unmetPrerequisites(prereqs: Prerequisite[]): Prerequisite[] {
    return prereqs.filter((prereq) => !prereq.met);
}

/** "a supplier and a raw material" · "a, b and c" — for a one-line reason. */
export function humanizeList(items: string[]): string {
    if (items.length <= 1) {
        return items[0] ?? '';
    }

    return `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}`;
}

/** "Add a supplier and a raw material first" — the disabled-button tooltip. */
export function prerequisiteReason(
    missing: Prerequisite[],
): string | undefined {
    if (missing.length === 0) {
        return undefined;
    }

    return `Add ${humanizeList(missing.map((prereq) => prereq.label))} first`;
}

/**
 * The empty state shown when a screen's prerequisites aren't met yet — it names
 * what's missing and links straight to each one, so a new user is never stuck at
 * a dead "create" button. Pair with a disabled `NewResourceButton` in the toolbar.
 */
export function PrereqEmptyState({
    icon,
    entity,
    missing,
}: {
    icon: ComponentType<{ className?: string }>;
    /** Singular, lower-case, e.g. "purchase order". */
    entity: string;
    missing: Prerequisite[];
}) {
    return (
        <EmptyState
            icon={icon}
            title={`Set up a few things first`}
            description={`Before you can create a ${entity}, add ${humanizeList(
                missing.map((prereq) => prereq.label),
            )}.`}
            action={
                <div className="flex flex-col gap-2 sm:flex-row">
                    {missing.map((prereq) => (
                        <Button key={prereq.label} asChild variant="outline">
                            <Link href={prereq.href}>
                                Add {prereq.label}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    ))}
                </div>
            }
        />
    );
}
