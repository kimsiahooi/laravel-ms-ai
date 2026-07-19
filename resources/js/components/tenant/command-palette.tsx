import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { tenantNavGroups } from '@/config/tenant-nav';
import { toUrl } from '@/lib/utils';

type TenantBrand = { slug: string; name: string } | null;

/**
 * The ⌘K / Ctrl-K command palette for the tenant workspace: a header trigger plus
 * a searchable dialog that jumps to any page. It reuses the same nav definition as
 * the sidebar ([`tenantNavGroups`](../../config/tenant-nav.ts)), so the two never
 * drift. Self-contained — mount it once in the header.
 */
export function CommandPalette() {
    const { tenant } = usePage().props as unknown as { tenant: TenantBrand };
    const slug = tenant?.slug ?? '';
    const [open, setOpen] = useState(false);

    const groups = tenantNavGroups(slug);

    // ⌘K / Ctrl-K toggles the palette (mirrors the sidebar's ⌘B shortcut).
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((previous) => !previous);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    const go = (href: string) => {
        setOpen(false);
        router.visit(href);
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                className="h-8 gap-2 text-muted-foreground"
                aria-label="Search pages"
            >
                <Search className="size-4" />
                <span className="hidden sm:inline">Search…</span>
                <CommandShortcut className="hidden sm:inline">
                    ⌘K
                </CommandShortcut>
            </Button>

            <CommandDialog
                open={open}
                onOpenChange={setOpen}
                title="Search pages"
                description="Jump to any page in your workspace"
            >
                <CommandInput placeholder="Search pages…" />
                <CommandList>
                    <CommandEmpty>No pages found.</CommandEmpty>
                    {groups.map((group, index) => (
                        <Fragment key={group.label ?? 'overview'}>
                            {index > 0 ? <CommandSeparator /> : null}
                            <CommandGroup heading={group.label}>
                                {group.items.map((item) => {
                                    const href = toUrl(item.href);

                                    return (
                                        <CommandItem
                                            key={href}
                                            value={`${group.label ?? ''} ${item.title}`}
                                            onSelect={() => go(href)}
                                        >
                                            {item.icon ? (
                                                <item.icon className="size-4" />
                                            ) : null}
                                            {item.title}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        </Fragment>
                    ))}
                </CommandList>
            </CommandDialog>
        </>
    );
}
