import type { FormComponentRef } from '@inertiajs/core';
import { useCallback, useRef } from 'react';
import type { ZodType } from 'zod';
import { runGate } from '@/lib/validation/gate';

/**
 * Wire a schema into an Inertia `<Form>`: spread the result onto the element.
 *
 *     const gate = useZodGate(categorySchema);
 *     <Form {...gate} noValidate action={…} method="post">…</Form>
 *
 * The values checked come from the form's own `getData()` — the exact object
 * Inertia is about to send — so the check and the request can never drift apart.
 * `noValidate` matters: without it the browser's own bubble fires first and this
 * never runs.
 *
 * With no schema the form submits as it always did, so forms can be converted one
 * at a time.
 */
export function useZodGate(schema?: ZodType) {
    const ref = useRef<FormComponentRef | null>(null);

    const onBefore = useCallback(
        () =>
            schema
                ? runGate(schema, ref.current?.getData(), ref.current)
                : true,
        [schema],
    );

    return { ref, onBefore };
}
