import { router } from '@inertiajs/react';
import { useState } from 'react';

type UseDeleteOptions<T> = {
    /** Resource base URL, e.g. `/${tenant.slug}/products`. */
    baseUrl: string;
    /** Called after a successful delete (e.g. flashToast) with the fresh page. */
    onDeleted?: (page: { props: unknown }) => void;
};

/**
 * Owns the delete-confirmation state + the `router.delete` call shared by every
 * resource list page. `request(item)` opens the confirm dialog; `confirm()`
 * deletes; `cancel()` dismisses. Pair with <ConfirmDeleteDialog item={deleting}/>.
 */
export function useDelete<T extends { id: number }>({
    baseUrl,
    onDeleted,
}: UseDeleteOptions<T>) {
    const [deleting, setDeleting] = useState<T | null>(null);

    const request = (item: T) => setDeleting(item);
    const cancel = () => setDeleting(null);

    const confirm = () => {
        if (!deleting) {
            return;
        }
        router.delete(`${baseUrl}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                setDeleting(null);
                onDeleted?.(page);
            },
        });
    };

    return { deleting, request, cancel, confirm };
}
