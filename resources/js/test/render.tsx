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

/**
 * Render a page as it looks when a submit has failed, or while one is in flight —
 * the error bag the server would have returned, and/or `processing`. Otherwise
 * identical to `renderPage`. Pass a function to target one form on a page that
 * holds several; it receives the form's identity (`useForm`'s first argument, or
 * `<Form>`'s `action`). The override is cleared after every test.
 */
export function renderPageWithForm(
    ui: ReactElement,
    props: Record<string, unknown>,
    form: NonNullable<typeof globalThis.__inertiaForm>,
): ReturnType<typeof render> {
    globalThis.__inertiaForm = form;

    return renderPage(ui, props);
}
