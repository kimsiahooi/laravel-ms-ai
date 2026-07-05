import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Tenant = {
    id: number;
    name: string;
    slug: string;
    created_at: string;
};

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    tenants: Tenant[];
    flash: { success: string | null };
};

export default function AdminDashboard() {
    const { auth, tenants, flash } = usePage().props as unknown as PageProps;

    return (
        <div className="mx-auto max-w-3xl space-y-8 p-8">
            <Head title="Admin" />

            <header className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">
                        Central administration
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Signed in as {auth.user?.email}
                    </p>
                </div>
                <Link
                    href="/admin/logout"
                    method="post"
                    as="button"
                    className="text-sm underline"
                >
                    Log out
                </Link>
            </header>

            {flash.success && (
                <div className="rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">
                    {flash.success}
                </div>
            )}

            <section className="space-y-4 rounded-lg border p-6">
                <h2 className="text-lg font-semibold">Create a tenant</h2>
                <Form
                    action="/admin/tenants"
                    method="post"
                    resetOnSuccess
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Organization name</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug (URL)</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    required
                                    placeholder="acme"
                                />
                                <InputError message={errors.slug} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="admin_name">
                                    First user name
                                </Label>
                                <Input
                                    id="admin_name"
                                    name="admin_name"
                                    required
                                />
                                <InputError message={errors.admin_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="admin_email">
                                    First user email
                                </Label>
                                <Input
                                    id="admin_email"
                                    name="admin_email"
                                    type="email"
                                    required
                                />
                                <InputError message={errors.admin_email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="admin_password">
                                    First user password
                                </Label>
                                <Input
                                    id="admin_password"
                                    name="admin_password"
                                    type="password"
                                    required
                                />
                                <InputError message={errors.admin_password} />
                            </div>
                            <div className="flex items-end sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Create tenant
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>

            <section className="space-y-3">
                <h2 className="text-lg font-semibold">
                    Tenants ({tenants.length})
                </h2>
                {tenants.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No tenants yet. Create one above.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {tenants.map((tenant) => (
                            <li
                                key={tenant.id}
                                className="flex items-center justify-between p-3"
                            >
                                <span className="font-medium">
                                    {tenant.name}
                                </span>
                                <a
                                    href={`/${tenant.slug}/login`}
                                    className="text-sm text-muted-foreground underline"
                                >
                                    /{tenant.slug}
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
