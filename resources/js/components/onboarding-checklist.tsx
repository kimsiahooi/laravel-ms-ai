import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Rocket } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

export type OnboardingStep = {
    /** Short imperative title, e.g. "Add a warehouse". */
    title: string;
    /** One plain-language sentence on what this step does. */
    description: string;
    /** Where the step's "New …" flow lives. */
    href: string;
    /** Whether the tenant has already completed this step. */
    done: boolean;
};

/**
 * A first-run getting-started checklist that walks a new tenant through the setup
 * chain (location → warehouse → catalog → bill of materials → stock in → first
 * order). Each step links to the page that completes it and shows done / next
 * from real counts. Renders nothing once every step is complete, so it quietly
 * disappears for an established workspace.
 */
export function OnboardingChecklist({
    steps,
    className,
}: {
    steps: OnboardingStep[];
    className?: string;
}) {
    const doneCount = steps.filter((step) => step.done).length;
    const total = steps.length;

    // Fully set up — nothing to nudge, so get out of the way.
    if (total === 0 || doneCount === total) {
        return null;
    }

    const nextIndex = steps.findIndex((step) => !step.done);
    const percent = Math.round((doneCount / total) * 100);

    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-start gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                        <Rocket className="size-4" />
                    </span>
                    <div className="min-w-0 space-y-1">
                        <CardTitle>Get your workspace ready</CardTitle>
                        <CardDescription>
                            {doneCount} of {total} done — finish setup to start
                            tracking stock and orders.
                        </CardDescription>
                    </div>
                </div>
                <Progress
                    value={percent}
                    className="mt-2"
                    aria-label={`Setup ${percent}% complete`}
                />
            </CardHeader>
            <CardContent>
                <ol className="space-y-1">
                    {steps.map((step, index) => {
                        const isNext = index === nextIndex;

                        return (
                            <li key={step.title}>
                                <Link
                                    href={step.href}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 transition-colors hover:bg-muted/60',
                                        isNext &&
                                            'border-primary/20 bg-primary/5 hover:bg-primary/10',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'grid size-6 shrink-0 place-items-center rounded-full font-semibold text-xs tabular-nums',
                                            step.done
                                                ? 'bg-primary text-primary-foreground'
                                                : isNext
                                                  ? 'bg-primary/15 text-primary'
                                                  : 'bg-secondary text-muted-foreground',
                                        )}
                                    >
                                        {step.done ? (
                                            <Check className="size-3.5" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <p
                                            className={cn(
                                                'font-medium text-sm',
                                                step.done &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {step.title}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {step.description}
                                        </p>
                                    </div>
                                    {isNext ? (
                                        <span className="flex shrink-0 items-center gap-1 font-medium text-primary text-xs">
                                            Next
                                            <ArrowRight className="size-3.5" />
                                        </span>
                                    ) : null}
                                </Link>
                            </li>
                        );
                    })}
                </ol>
            </CardContent>
        </Card>
    );
}
