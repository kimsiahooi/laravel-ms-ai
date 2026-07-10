import { usePage } from '@inertiajs/react';

/**
 * Typed accessor for Inertia page props. Inertia types `usePage().props`
 * loosely, so pages would otherwise repeat `usePage().props as unknown as
 * PageProps`; this keeps the single unavoidable cast in one place.
 */
export function usePageProps<T>(): T {
    return usePage().props as unknown as T;
}
