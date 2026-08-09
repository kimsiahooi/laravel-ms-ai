import type { ZodType } from 'zod';

/**
 * Client-side validation that runs *before* the request leaves the browser, and
 * reports failures through the very same channel the server's do.
 *
 * Inertia keys validation errors by dot path (`items.0.quantity`), and zod's
 * `issue.path` is `['items', 0, 'quantity']` — the two join up exactly. So a form
 * needs no separate notion of "client errors": both sources write to one bag, and
 * the `<InputError>` already under each field renders whichever arrived.
 */

/** A field path → its first message. The shape Laravel returns. */
export type ErrorBag = Record<string, string>;

/** Where an issue with no field of its own is filed (a whole-form rule). */
export const FORM_ERROR_KEY = 'form';

/**
 * The part of Inertia's form bag this needs. Both the `<Form>` ref and `useForm`
 * satisfy it, which is why one gate serves every form in the app.
 */
export type Gateable = {
    setError: (errors: ErrorBag) => void;
    // No arguments: Inertia types this per form key, and clearing the lot is all
    // that's needed here — a narrower signature still satisfies this one.
    clearErrors: () => void;
};

/**
 * Check `values`, and return the failures keyed the way the server would key them.
 * Returns null when everything passed.
 *
 * Only the first issue per field is kept — more than one message under a single
 * input is noise, and it matches what Laravel sends.
 */
export function zodErrors(schema: ZodType, values: unknown): ErrorBag | null {
    const result = schema.safeParse(values);

    if (result.success) {
        return null;
    }

    const errors: ErrorBag = {};

    for (const issue of result.error.issues) {
        const key = issue.path.map(String).join('.') || FORM_ERROR_KEY;

        if (!(key in errors)) {
            errors[key] = issue.message;
        }
    }

    return errors;
}

/** `items.0.quantity` → `items[0][quantity]`, the name the input carries. */
export function dotPathToInputName(path: string): string {
    const [head, ...rest] = path.split('.');

    return rest.reduce((name, part) => `${name}[${part}]`, head);
}

/**
 * Validate, publish any messages into the form's error bag, and say whether the
 * request may go ahead. Returning false from a visit's `onBefore` makes Inertia
 * return before it builds the request, so nothing is sent and the form never
 * enters its submitting state.
 */
export function runGate(
    schema: ZodType,
    values: unknown,
    bag: Gateable | null | undefined,
): boolean {
    // A form whose ref hasn't attached yet should still submit — a wiring mistake
    // must not silently make the page unusable.
    if (!bag) {
        return true;
    }

    const errors = zodErrors(schema, values);

    bag.clearErrors();

    if (!errors) {
        return true;
    }

    bag.setError(errors);
    focusFirstInvalid(errors);

    return false;
}

/** Put the cursor on the first thing that needs fixing. */
function focusFirstInvalid(errors: ErrorBag): void {
    const [first] = Object.keys(errors);

    if (!first || typeof document === 'undefined') {
        return;
    }

    const forSelector = (value: string) =>
        typeof CSS !== 'undefined' && CSS.escape
            ? CSS.escape(value)
            : value.replace(/["\\]/g, '\\$&');

    const field = document.querySelector<HTMLElement>(
        `[name="${forSelector(dotPathToInputName(first))}"], #${forSelector(first)}`,
    );

    field?.focus();
}
