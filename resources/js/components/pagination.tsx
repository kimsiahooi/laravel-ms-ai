import { Slot } from 'radix-ui';
import type * as React from 'react';
import { type Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PaginationLinkProps = {
    isActive?: boolean;
    asChild?: boolean;
} & Pick<React.ComponentProps<typeof Button>, 'size'> &
    React.ComponentProps<'a'>;

// Same markup as shadcn's PaginationLink, but the active page uses the primary
// (`default`) button variant — a filled indigo chip — instead of `outline`, and
// it supports `asChild` so the styling can wrap an Inertia <Link> (SPA navigation
// with correct modifier-click / open-in-new-tab handling). Composes buttonVariants
// rather than editing components/ui/pagination.tsx (vendored, read-only) — see
// docs/CODING-STANDARDS.md.
export function PaginationLink({
    className,
    isActive,
    size = 'icon',
    asChild = false,
    ...props
}: PaginationLinkProps) {
    const Comp = asChild ? Slot.Root : 'a';

    return (
        <Comp
            aria-current={isActive ? 'page' : undefined}
            data-slot="pagination-link"
            data-active={isActive}
            className={cn(
                buttonVariants({
                    variant: isActive ? 'default' : 'ghost',
                    size,
                }),
                className,
            )}
            {...props}
        />
    );
}
