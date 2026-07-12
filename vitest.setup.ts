// Registers the jest-dom matchers (toBeInTheDocument, toHaveTextContent, …) on
// Vitest's `expect`, for every test file.
import '@testing-library/jest-dom/vitest';
import { createElement } from 'react';
import { vi } from 'vitest';

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
