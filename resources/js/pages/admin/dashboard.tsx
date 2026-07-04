import { Head, usePage } from '@inertiajs/react';

export default function AdminDashboard() {
    const { auth } = usePage().props as unknown as {
        auth: { user: { name: string; email: string } | null };
    };

    return (
        <div className="p-8">
            <Head title="Admin" />
            <h1 className="text-2xl font-bold">Central administration</h1>
            <p className="text-muted-foreground">
                Signed in as {auth.user?.name} ({auth.user?.email})
            </p>
        </div>
    );
}
