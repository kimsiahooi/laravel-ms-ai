import { Head, usePage } from '@inertiajs/react';

export default function TenantDashboard() {
    const { auth, tenant } = usePage().props as unknown as {
        auth: { user: { name: string; email: string } | null };
        tenant: { slug: string; name: string } | null;
    };

    return (
        <div className="p-8">
            <Head title={tenant?.name ?? 'Dashboard'} />
            <h1 className="text-2xl font-bold">{tenant?.name}</h1>
            <p className="text-muted-foreground">
                Signed in as {auth.user?.name} ({auth.user?.email})
            </p>
        </div>
    );
}
