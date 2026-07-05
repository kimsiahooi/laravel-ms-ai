import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

type TenantBrand = { slug: string; name: string } | null;

export default function TenantLogo() {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-semibold leading-tight">
                    {tenant?.name ?? 'Workspace'}
                </span>
                <span className="truncate text-muted-foreground text-xs leading-tight">
                    Workspace
                </span>
            </div>
        </>
    );
}
