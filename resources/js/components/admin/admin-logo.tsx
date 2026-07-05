import AppLogoIcon from '@/components/app-logo-icon';

export default function AdminLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-semibold leading-tight">
                    MS&#8209;AI Platform
                </span>
                <span className="truncate text-muted-foreground text-xs leading-tight">
                    Central administration
                </span>
            </div>
        </>
    );
}
