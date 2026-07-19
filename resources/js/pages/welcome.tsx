import { Head, usePage } from '@inertiajs/react';
import { ArrowRight, Boxes, Factory, Layers } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type WelcomeProps = {
    auth: { user: { name: string } | null };
};

const FEATURES = [
    {
        icon: Boxes,
        title: 'Stock you can trust',
        description:
            'Live on-hand levels, movements, transfers and stock takes across every warehouse.',
    },
    {
        icon: Factory,
        title: 'Make what you sell',
        description:
            'Turn raw materials into finished products with bills of materials and production orders.',
    },
    {
        icon: Layers,
        title: 'A workspace per tenant',
        description:
            'Every organisation gets an isolated database and its own branded workspace.',
    },
];

export default function Welcome() {
    const { auth } = usePage<WelcomeProps>().props;
    const signedIn = Boolean(auth?.user);

    return (
        <>
            <Head title="MS-AI Platform — inventory & production for manufacturers" />

            <div className="min-h-dvh bg-linear-to-b from-background to-muted/40 text-foreground">
                <div className="mx-auto flex min-h-dvh max-w-5xl flex-col px-6 py-6">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <span className="font-semibold">
                                MS-AI Platform
                            </span>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <a href="/admin">
                                {signedIn ? 'Open console' : 'Sign in'}
                            </a>
                        </Button>
                    </header>

                    <main className="flex flex-1 flex-col justify-center py-16">
                        <div className="max-w-2xl space-y-6">
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 font-medium text-primary text-xs">
                                <Factory className="size-3" />
                                Built for manufacturers
                            </span>
                            <h1 className="text-balance font-semibold text-4xl tracking-tight sm:text-5xl">
                                Inventory and production, under control.
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                One place to track raw materials, run production
                                and manage purchase &amp; sales orders across
                                every warehouse — with an isolated workspace for
                                each organisation.
                            </p>
                            <div className="flex flex-wrap items-center gap-3 pt-2">
                                <Button asChild size="lg">
                                    <a href="/admin">
                                        {signedIn
                                            ? 'Open the console'
                                            : 'Sign in to the console'}
                                        <ArrowRight className="size-4" />
                                    </a>
                                </Button>
                                <p className="text-muted-foreground text-sm">
                                    Tenant members sign in at their own
                                    workspace URL.
                                </p>
                            </div>
                        </div>

                        <div className="mt-16 grid gap-4 sm:grid-cols-3">
                            {FEATURES.map(
                                ({ icon: Icon, title, description }) => (
                                    <Card key={title}>
                                        <CardHeader>
                                            <span className="flex size-9 items-center justify-center rounded-lg bg-secondary text-foreground">
                                                <Icon className="size-4" />
                                            </span>
                                            <CardTitle className="text-base">
                                                {title}
                                            </CardTitle>
                                            <CardDescription>
                                                {description}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                ),
                            )}
                        </div>
                    </main>

                    <footer className="border-border/60 border-t pt-6 text-muted-foreground text-xs">
                        &copy; {2026} MS-AI Platform · Inventory &amp;
                        production management
                    </footer>
                </div>
            </div>
        </>
    );
}
