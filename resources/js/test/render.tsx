import { render } from '@testing-library/react';
import type { ReactElement } from 'react';
import { TooltipProvider } from '@/components/ui/tooltip';

/**
 * Render a page/component with mocked Inertia page props. The `@inertiajs/react`
 * mock in vitest.setup.ts reads these off `globalThis`, so `usePage()` /
 * `usePageProps()` resolve to them. Wraps the tree in the app-shell-level providers
 * pages assume (TooltipProvider), so components using Radix Tooltip render outside
 * the real layout. Use this for page render-smoke + hydration tests.
 */
export function renderPage(
    ui: ReactElement,
    props: Record<string, unknown>,
): ReturnType<typeof render> {
    globalThis.__inertiaProps = props;

    return render(<TooltipProvider>{ui}</TooltipProvider>);
}
