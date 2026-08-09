import { fireEvent, render } from '@testing-library/react';
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

/**
 * The requests a real form actually sent this test. A submit that client-side
 * validation refused never reaches the router, so an empty mock is the assertion
 * for "the browser didn't send it".
 *
 * Only meaningful with `renderPage` — `renderPageWithForm` swaps the real `<Form>`
 * for a stub, so no gate runs and nothing is ever recorded.
 */
export function submittedVisits(): NonNullable<
    typeof globalThis.__inertiaVisits
> {
    const visits = globalThis.__inertiaVisits;

    if (!visits) {
        throw new Error(
            'The Inertia router spy is not installed — is vitest.setup.ts loaded?',
        );
    }

    return visits;
}

/**
 * Submit the form `element` sits in, the way its submit button would. Uses
 * `fireEvent.submit` rather than `requestSubmit` so jsdom's native constraint
 * validation can't short-circuit the submit before our own validation runs.
 */
export function submitForm(element: HTMLElement): void {
    const form = element.closest('form');

    if (!form) {
        throw new Error('That element is not inside a <form>.');
    }

    fireEvent.submit(form);
}
