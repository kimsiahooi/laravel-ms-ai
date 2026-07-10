import { useState } from 'react';

type UseResourceDialogOptions<T> = {
    /** Reset the form fields for a new record (called by openCreate). */
    onCreate?: () => void;
    /** Fill the form fields from an existing record (called by openEdit). */
    onEdit?: (item: T) => void;
};

/**
 * Owns the create/edit dialog state shared by every resource list page: whether
 * the dialog is open and which record (if any) is being edited. The per-page
 * field state stays in the page; pass `onCreate`/`onEdit` to reset/fill it.
 */
export function useResourceDialog<T>({
    onCreate,
    onEdit,
}: UseResourceDialogOptions<T> = {}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<T | null>(null);

    const openCreate = () => {
        setEditing(null);
        onCreate?.();
        setOpen(true);
    };

    const openEdit = (item: T) => {
        setEditing(item);
        onEdit?.(item);
        setOpen(true);
    };

    const close = () => setOpen(false);

    const onOpenChange = (next: boolean) => {
        if (!next) {
            setOpen(false);
        }
    };

    return { open, editing, openCreate, openEdit, close, onOpenChange };
}
