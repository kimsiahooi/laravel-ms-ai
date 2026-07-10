import { toast } from 'sonner';

/**
 * Surface the one-shot `flash.success` message (if any) from a fresh Inertia
 * page. Pass as an Inertia `onSuccess` handler (or `useDelete`'s `onDeleted`):
 * `<Form onSuccess={flashToast} />`.
 */
export function flashToast(page: { props: unknown }): void {
    const message = (page.props as { flash?: { success?: string | null } })
        .flash?.success;

    if (message) {
        toast.success(message);
    }
}
