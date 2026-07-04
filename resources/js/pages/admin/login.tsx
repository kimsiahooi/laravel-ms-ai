import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function AdminLogin() {
    return (
        <div className="flex min-h-screen items-center justify-center p-6">
            <Head title="Admin log in" />

            <Form
                action="/admin/login"
                method="post"
                resetOnSuccess={['password']}
                className="w-full max-w-sm space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-1 text-center">
                            <h1 className="text-xl font-semibold">
                                Super-admin sign in
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Central administration
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoFocus
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autoComplete="current-password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            Log in
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
