import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem } from '@/types';

export function AdminSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-sidebar-border/50 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() =>
                            updateAppearance(isDark ? 'light' : 'dark')
                        }
                        aria-label="Toggle theme"
                    >
                        {/* Both icons render identically on server + client; the
                            `.dark` class (applied before hydration) decides which
                            one shows. No JS theme branch here means no hydration
                            mismatch — the server can't know the stored theme. */}
                        <Sun className="hidden size-4 dark:block" />
                        <Moon className="size-4 dark:hidden" />
                        <span className="sr-only">Toggle theme</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Toggle theme</TooltipContent>
            </Tooltip>
        </header>
    );
}
