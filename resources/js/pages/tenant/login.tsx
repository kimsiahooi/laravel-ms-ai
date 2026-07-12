import { Form, Head } from '@inertiajs/react';
import {
    ArrowRight,
    Database,
    Eye,
    EyeOff,
    LoaderCircle,
    Lock,
    Mail,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageProps } from '@/hooks/use-page-props';
import { store as loginStore } from '@/routes/tenant/login';
import type { TenantBrand } from '@/types';

const HIGHLIGHTS = [
    {
        icon: ShieldCheck,
        title: 'Secure access',
        description: 'Protected sign-in to your workspace.',
    },
    {
        icon: Database,
        title: 'Private data',
        description:
            'Your data is kept completely separate from other organizations on this platform.',
    },
    {
        icon: Users,
        title: 'Built for your team',
        description: 'One shared space for everyone in the organization.',
    },
];

export default function TenantLogin() {
    const { tenant } = usePageProps<{ tenant: TenantBrand }>();
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <Head title={`Sign in — ${tenant.name}`} />

            {/* Brand panel — hidden on small screens */}
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-zinc-950 p-10 text-zinc-100 lg:flex">
                <div
                    aria-hidden
                    className="absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:36px_36px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"
                />
                <div
                    aria-hidden
                    className="absolute -top-32 -left-24 size-96 rounded-full bg-white/10 blur-3xl"
                />
                <div
                    aria-hidden
                    className="absolute -right-24 -bottom-32 size-96 rounded-full bg-white/5 blur-3xl"
                />

                <div className="relative z-10 flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15 backdrop-blur">
                        <AppLogoIcon className="size-5 text-white" />
                    </span>
                    <div className="leading-tight">
                        <p className="font-semibold">{tenant.name}</p>
                        <p className="text-xs text-zinc-400">Workspace</p>
                    </div>
                </div>

                <div className="relative z-10 space-y-8">
                    <div className="space-y-3">
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1 font-medium font-mono text-xs text-zinc-300">
                            /{tenant.slug}
                        </span>
                        <h2 className="text-balance font-semibold text-2xl tracking-tight">
                            Welcome back to {tenant.name}.
                        </h2>
                        <p className="max-w-sm text-sm text-zinc-400">
                            Sign in to continue to your workspace.
                        </p>
                    </div>

                    <ul className="space-y-4">
                        {HIGHLIGHTS.map(
                            ({ icon: Icon, title, description }) => (
                                <li
                                    key={title}
                                    className="flex items-start gap-3"
                                >
                                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/10">
                                        <Icon className="size-4 text-zinc-200" />
                                    </span>
                                    <div className="space-y-0.5">
                                        <p className="font-medium text-sm text-zinc-100">
                                            {title}
                                        </p>
                                        <p className="text-xs text-zinc-400">
                                            {description}
                                        </p>
                                    </div>
                                </li>
                            ),
                        )}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-zinc-500">
                    &copy; {2026} {tenant.name}
                </p>
            </aside>

            {/* Form panel */}
            <main className="flex items-center justify-center bg-background px-6 py-12">
                <div className="w-full max-w-sm">
                    {/* Compact brand shown only when the panel is hidden */}
                    <div className="mb-8 flex items-center justify-center gap-2 lg:hidden">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span className="font-semibold">{tenant.name}</span>
                    </div>

                    <Card className="border-border/70 shadow-black/[0.03] shadow-lg">
                        <CardHeader className="space-y-2">
                            <span className="flex size-11 items-center justify-center rounded-full bg-secondary text-foreground">
                                <ShieldCheck className="size-5" />
                            </span>
                            <CardTitle className="text-xl">
                                Sign in to {tenant.name}
                            </CardTitle>
                            <CardDescription>
                                Enter your email and password to access this
                                workspace.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <Form
                                action={loginStore.url({ tenant: tenant.slug })}
                                method="post"
                                resetOnSuccess={['password']}
                                disableWhileProcessing
                                className="space-y-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email</Label>
                                            <div className="relative">
                                                <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    required
                                                    autoFocus
                                                    autoComplete="email"
                                                    placeholder="you@example.com"
                                                    className="pl-9"
                                                    aria-invalid={
                                                        !!errors.email
                                                    }
                                                    aria-describedby={
                                                        errors.email
                                                            ? 'email-error'
                                                            : undefined
                                                    }
                                                />
                                            </div>
                                            <InputError
                                                id="email-error"
                                                role="alert"
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            <div className="relative">
                                                <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    id="password"
                                                    name="password"
                                                    type={
                                                        showPassword
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    required
                                                    autoComplete="current-password"
                                                    placeholder="••••••••"
                                                    className="px-9"
                                                    aria-invalid={
                                                        !!errors.password
                                                    }
                                                    aria-describedby={
                                                        errors.password
                                                            ? 'password-error'
                                                            : undefined
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowPassword(
                                                            (v) => !v,
                                                        )
                                                    }
                                                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    aria-label={
                                                        showPassword
                                                            ? 'Hide password'
                                                            : 'Show password'
                                                    }
                                                >
                                                    {showPassword ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </button>
                                            </div>
                                            <InputError
                                                id="password-error"
                                                role="alert"
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                            />
                                            <Label
                                                htmlFor="remember"
                                                className="font-normal text-muted-foreground text-sm"
                                            >
                                                Remember this device
                                            </Label>
                                        </div>

                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <LoaderCircle className="size-4 animate-spin" />
                                            ) : (
                                                <>
                                                    Sign in
                                                    <ArrowRight className="size-4" />
                                                </>
                                            )}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <p className="mt-6 flex items-center justify-center gap-1.5 text-center text-muted-foreground text-xs">
                        <Lock className="size-3" />
                        Secure workspace &middot; {tenant.name}
                    </p>
                </div>
            </main>
        </div>
    );
}
