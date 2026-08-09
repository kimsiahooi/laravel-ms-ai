// Registers the jest-dom matchers (toBeInTheDocument, toHaveTextContent, …) on
// Vitest's `expect`, for every test file.
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { createElement } from 'react';
import { afterEach, vi } from 'vitest';

// `globals: false` in vitest.config, so Testing Library's auto-cleanup never
// registers — unmount + reset the DOM after each test ourselves, otherwise a file
// with two render tests leaks the first render into the second.
afterEach(() => {
    cleanup();
    // Never let one test's injected form failure leak into the next.
    globalThis.__inertiaForm = undefined;
});

// --- Form failure / loading injection -------------------------------------------
// Validation errors and `processing` live in Inertia-internal form state that only a
// real submit mutates, and no component reads them off page props — so a test can
// only reach them here, at the mock boundary. Both hooks below consult an OPT-IN
// override (globalThis.__inertiaForm, set by test/render.tsx): unset means the real
// behaviour, so tests that don't ask for a failure are completely unaffected.
type FormOverride = {
    errors?: Record<string, string>;
    processing?: boolean;
    onSubmit?: 'error' | 'success';
    isDirty?: boolean;
};

/** Resolve the override for one form, identified by its data key or action URL. */
function formOverride(key?: unknown): FormOverride | undefined {
    const override = globalThis.__inertiaForm;

    return typeof override === 'function' ? override(key) : override;
}

/** The render bag an Inertia <Form> hands its children. */
function formBag(override: FormOverride) {
    const errors = override.errors ?? {};

    return {
        errors,
        hasErrors: Object.keys(errors).length > 0,
        processing: override.processing ?? false,
        isDirty: override.isDirty ?? false,
        progress: null,
        wasSuccessful: false,
        recentlySuccessful: false,
        clearErrors: () => {},
        resetAndClearErrors: () => {},
        setError: () => {},
        reset: () => {},
        defaults: () => {},
        submit: () => {},
    };
}

// --- jsdom polyfills the app's UI relies on -------------------------------------
// recharts' ResponsiveContainer observes size; DateRangePicker reads matchMedia.
// jsdom implements neither, so provide inert stand-ins.
if (!('ResizeObserver' in globalThis)) {
    globalThis.ResizeObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
}

// cmdk (the ⌘K command palette) scrolls the active item into view on select;
// jsdom has no layout, so stub it out.
if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = () => {};
}

if (!window.matchMedia) {
    window.matchMedia = (query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener() {},
        removeEventListener() {},
        addListener() {},
        removeListener() {},
        dispatchEvent() {
            return false;
        },
    });
}

// --- Inertia page context -------------------------------------------------------
// Page tests set globalThis.__inertiaProps (via resources/js/test/render helper);
// usePage()/usePageProps() then resolve to them. Everything else in the module stays
// real (useForm, etc.); only the browser-coupled bits are stubbed.
vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/react')>();

    return {
        ...actual,
        usePage: () => ({
            props: globalThis.__inertiaProps ?? {},
            url: '/',
            component: 'test',
            version: null,
        }),
        Head: () => null,
        Link: ({ children, href, ...rest }: Record<string, unknown>) =>
            createElement(
                'a',
                { href: typeof href === 'string' ? href : '#', ...rest },
                children as never,
            ),
        // Wrapped, never replaced: data/setData/transform keep working, and only the
        // injected error bag + processing flag are layered on. `onSubmit` swaps the
        // submit methods for ones that call the caller's own onError/onSuccess
        // synchronously, which is how the toast.error paths get exercised.
        useForm: (...args: unknown[]) => {
            const form = (
                actual.useForm as unknown as (
                    ...a: unknown[]
                ) => Record<string, unknown>
            )(...args);
            const override = formOverride(args[0]);

            if (!override) {
                return form;
            }

            const errors = {
                ...(form.errors as Record<string, string>),
                ...(override.errors ?? {}),
            };
            const submit = (...call: unknown[]) => {
                const options = (call.at(-1) ?? {}) as Record<
                    string,
                    ((payload: unknown) => void) | undefined
                >;
                if (override.onSubmit === 'success') {
                    options.onSuccess?.({ props: {} });
                } else {
                    options.onError?.(override.errors ?? {});
                }
                options.onFinish?.({});
            };

            return {
                ...form,
                errors,
                hasErrors: Object.keys(errors).length > 0,
                processing: override.processing ?? form.processing,
                ...(override.onSubmit
                    ? {
                          post: submit,
                          put: submit,
                          patch: submit,
                          delete: submit,
                          submit,
                      }
                    : {}),
            };
        },
        // <Form> builds its bag from an internal useForm imported straight from
        // @inertiajs/core — an externalized dependency this mock cannot reach — so
        // when an override is set we stand in a plain <form> yielding the injected
        // bag. With no override it falls through to the real component untouched.
        Form: (props: Record<string, unknown>) => {
            const override = formOverride(props.action);

            if (!override) {
                return createElement(actual.Form as never, props as never);
            }

            const bag = formBag(override);

            return createElement(
                'form',
                {
                    className: props.className,
                    onSubmit: (event: { preventDefault: () => void }) =>
                        event.preventDefault(),
                },
                typeof props.children === 'function'
                    ? (props.children as (bag: unknown) => unknown)(bag)
                    : (props.children as never),
            );
        },
        router: {
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            visit: vi.fn(),
            reload: vi.fn(),
        },
    };
});
