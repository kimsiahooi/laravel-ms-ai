import { ChevronDown, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

export type HowItWorksStep = {
    /** Short imperative title, e.g. "Add a supplier". */
    title: string;
    /** One plain-language sentence explaining the step. */
    description: ReactNode;
};

type HowItWorksProps = {
    /** Header label; defaults to "How this works". */
    title?: string;
    steps: HowItWorksStep[];
    /** Whether the panel starts expanded. Keep deterministic for SSR. */
    defaultOpen?: boolean;
    className?: string;
};

/**
 * A collapsible, numbered "how this works" card for the top of a multi-step flow
 * (or its empty state). Composes the vendored `ui/collapsible` — a soft indigo
 * surface with a step-by-step list, so a new user can learn the flow without
 * leaving the page. Purely presentational; pass the steps that fit the screen.
 */
export function HowItWorks({
    title = 'How this works',
    steps,
    defaultOpen = false,
    className,
}: HowItWorksProps) {
    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className={cn(
                'rounded-xl border border-primary/15 bg-primary/5',
                className,
            )}
        >
            <CollapsibleTrigger className="group flex w-full items-center gap-2 px-4 py-3 text-left font-medium text-sm">
                <Sparkles
                    className="size-4 shrink-0 text-primary"
                    aria-hidden
                />
                <span className="flex-1">{title}</span>
                <ChevronDown
                    className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180"
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ol className="space-y-3 px-4 pt-1 pb-4">
                    {steps.map((step, index) => (
                        <li key={step.title} className="flex gap-3">
                            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-primary/10 font-semibold text-primary text-xs tabular-nums">
                                {index + 1}
                            </span>
                            <div className="space-y-0.5">
                                <p className="font-medium text-sm leading-6">
                                    {step.title}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {step.description}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            </CollapsibleContent>
        </Collapsible>
    );
}
