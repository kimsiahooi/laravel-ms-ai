// `tsc` type-checks resources/js/** (including *.test.tsx) but not the root
// vitest.setup.ts, so the jest-dom matcher augmentation is referenced here — inside
// the tsconfig include — to make matchers like toBeInTheDocument() type-check.
import '@testing-library/jest-dom/vitest';

/**
 * A form's failure/loading state for one test. Inertia keeps `errors` and
 * `processing` in state that only a real submit mutates, so tests inject them at
 * the `@inertiajs/react` mock boundary instead (see vitest.setup.ts).
 */
type InertiaFormOverride = {
    /** The error bag a rejected submit would have produced. */
    errors?: Record<string, string>;
    /** Freeze the form mid-submit (disabled buttons, spinners). */
    processing?: boolean;
    /** Make submitting call the page's own onError/onSuccess, without a request. */
    onSubmit?: 'error' | 'success';
    /** Seen by the dialog as unsaved edits (drives the discard prompt). */
    isDirty?: boolean;
};

declare global {
    /** Inertia page props for the current test render (set by test/render.tsx). */
    var __inertiaProps: Record<string, unknown> | undefined;

    /**
     * Form override for the current test (set by test/render.tsx). Pass a function
     * to target one form on a page that holds several — it receives the form's
     * identity (`useForm`'s first argument, or `<Form>`'s `action`).
     */
    var __inertiaForm:
        | InertiaFormOverride
        | ((key?: unknown) => InertiaFormOverride | undefined)
        | undefined;
}
