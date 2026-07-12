import { Head } from '@inertiajs/react';
import { BadgeCheck, CalendarDays, ShieldCheck, Users } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDate } from '@/lib/format';
import type { TenantBrand, User } from '@/types';

type Organization = {
    name: string;
    slug: string;
    logo: string | null;
    created_at: string | null;
    members: number;
};

type PageProps = {
    auth: { user: User | null };
    tenant: TenantBrand | null;
    organization: Organization;
};

export default function TenantDashboard() {
    const { auth, tenant, organization } = usePageProps<PageProps>();

    const getInitials = useInitials();
    const user = auth.user;
    const emailVerified = Boolean(user?.email_verified_at);
    const twoFactorOn = Boolean(user?.two_factor_enabled);

    return (
        <TenantLayout>
            <Head title={`Dashboard — ${tenant?.name ?? 'Workspace'}`} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Dashboard
                </h1>
                <p className="text-muted-foreground text-sm">
                    Your account and organization.
                </p>
            </div>

            {/* Identity band: the signed-in user + the current organization */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Card>
                    <CardContent className="flex items-start gap-4 p-5">
                        <Avatar className="size-12 rounded-full">
                            <AvatarImage
                                src={user?.avatar}
                                alt={user?.name ?? ''}
                            />
                            <AvatarFallback className="bg-primary/10 font-medium text-primary">
                                {getInitials(user?.name ?? '')}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                You
                            </p>
                            <p className="truncate font-semibold text-foreground">
                                {user?.name ?? '—'}
                            </p>
                            <p className="truncate text-muted-foreground text-sm">
                                {user?.email}
                            </p>
                            <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                                {emailVerified ? (
                                    <Badge
                                        variant="secondary"
                                        className="gap-1"
                                    >
                                        <BadgeCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                        Email verified
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="text-muted-foreground"
                                    >
                                        Email not verified
                                    </Badge>
                                )}
                                {twoFactorOn && (
                                    <Badge
                                        variant="secondary"
                                        className="gap-1"
                                    >
                                        <ShieldCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                        2FA enabled
                                    </Badge>
                                )}
                                {user?.created_at && (
                                    <span className="text-muted-foreground text-xs">
                                        · Member since{' '}
                                        {formatDate(user.created_at)}
                                    </span>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex items-start gap-4 p-5">
                        <Avatar className="size-12 rounded-lg">
                            <AvatarImage
                                src={organization.logo ?? undefined}
                                alt={organization.name}
                            />
                            <AvatarFallback className="rounded-lg bg-primary/10 font-medium text-primary">
                                {getInitials(organization.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                Organization
                            </p>
                            <p className="truncate font-semibold text-foreground">
                                {organization.name}
                            </p>
                            <p className="truncate font-mono text-muted-foreground text-sm">
                                /{organization.slug}
                            </p>
                            <div className="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground text-xs">
                                <span className="flex items-center gap-1">
                                    <Users className="size-3.5" />
                                    {organization.members}{' '}
                                    {organization.members === 1
                                        ? 'member'
                                        : 'members'}
                                </span>
                                {organization.created_at && (
                                    <span className="flex items-center gap-1">
                                        <CalendarDays className="size-3.5" />
                                        Created{' '}
                                        {formatDate(organization.created_at)}
                                    </span>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </TenantLayout>
    );
}
