import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Compass, Lock, ServerCrash } from 'lucide-react';
import type { ComponentType } from 'react';
import { Button } from '@/components/ui/button';

type PageProps = {
    status: number;
    /** Where "go back" leads — the dashboard/home for the area the error hit. */
    homeUrl: string;
    /** The label for that button, e.g. "Back to dashboard". */
    homeLabel: string;
};

type ErrorCopy = {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
};

// Plain-language copy per status — written for the person running the business,
// never a raw HTTP phrase. Unknown statuses fall back to the generic message.
const COPY: Record<number, ErrorCopy> = {
    403: {
        icon: Lock,
        title: "You don't have access to this",
        description:
            'This area is limited to certain roles. Ask an admin to grant you access if you need it.',
    },
    404: {
        icon: Compass,
        title: "We couldn't find that page",
        description:
            "The page you're looking for doesn't exist or may have moved.",
    },
    500: {
        icon: ServerCrash,
        title: 'Something went wrong on our end',
        description:
            'An unexpected error stopped this page from loading. Please try again in a moment.',
    },
};

const FALLBACK: ErrorCopy = {
    icon: ServerCrash,
    title: 'Something went wrong',
    description: 'This page could not be loaded. Please try again in a moment.',
};

export default function ErrorPage({ status, homeUrl, homeLabel }: PageProps) {
    const { icon: Icon, title, description } = COPY[status] ?? FALLBACK;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background px-6 py-16 text-center">
            <Head title={title} />

            <div className="flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20">
                <Icon className="size-8" />
            </div>

            <div className="flex max-w-md flex-col gap-2">
                <p className="font-medium font-mono text-muted-foreground text-sm tracking-widest">
                    {status}
                </p>
                <h1 className="font-semibold text-2xl text-foreground tracking-tight sm:text-3xl">
                    {title}
                </h1>
                <p className="text-muted-foreground text-sm leading-relaxed">
                    {description}
                </p>
            </div>

            <Button asChild>
                <Link href={homeUrl}>
                    <ArrowLeft className="size-4" />
                    {homeLabel}
                </Link>
            </Button>
        </div>
    );
}
