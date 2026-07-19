import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, NavItem } from '@/types';

/**
 * The sidebar navigation. Pass `groups` for labelled sections (Catalog, Stock, …)
 * or `items` for a single flat, unlabelled list. Each group renders its own
 * SidebarGroup so section headings scan cleanly for a long nav.
 */
export function NavMain({
    items,
    groups,
}: {
    items?: NavItem[];
    groups?: NavGroup[];
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const sections: NavGroup[] = groups ?? [{ items: items ?? [] }];

    return (
        <>
            {sections.map((section, index) => (
                <SidebarGroup
                    key={section.label ?? `section-${index}`}
                    className="px-2 py-0"
                >
                    {section.label ? (
                        <SidebarGroupLabel>{section.label}</SidebarGroupLabel>
                    ) : null}
                    <SidebarMenu>
                        {section.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link
                                        href={item.href}
                                        prefetch={item.prefetch}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
