import { Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * Legacy starter route (web guard) — superseded by the central admin and tenant
 * dashboards. Kept so the app shell still resolves; anyone who lands here is sent
 * on to the admin console rather than shown placeholder boxes.
 */
export default function Dashboard() {
    useEffect(() => {
        window.location.replace('/admin');
    }, []);

    return (
        <>
            <Head title="Redirecting…" />
            <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-background text-foreground">
                <span className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-5" />
                </span>
                <p className="flex items-center gap-2 text-muted-foreground text-sm">
                    <LoaderCircle className="size-4 animate-spin" />
                    Taking you to the console…
                </p>
            </div>
        </>
    );
}
