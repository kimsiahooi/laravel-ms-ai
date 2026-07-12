import { Form } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type FormRender = {
    processing: boolean;
    errors: Record<string, string>;
};

type ResourceFormDialogProps<T extends { id: number }> = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The record being edited, or null when creating. */
    editing: T | null;
    /** Lowercase singular, e.g. "raw material" → "New raw material" / "Create raw material". */
    entityLabel: string;
    /** Resource base URL; the action posts to `baseUrl` (create) or `baseUrl/{id}` (edit). */
    baseUrl: string;
    /** Optional callback with the fresh page after a successful save; the dialog auto-closes regardless. (Success toasts come from the global useFlashToast hook.) */
    onSuccess?: (page: { props: unknown }) => void;
    /** Override the default header descriptions. */
    description?: { create: ReactNode; edit: ReactNode };
    /** Extra classes for DialogContent (e.g. a tall scrolling form). */
    contentClassName?: string;
    /** The form fields, rendered inside the Inertia <Form> with its render props. */
    children: (form: FormRender) => ReactNode;
};

/**
 * The shared create/edit dialog shell for a resource: the Dialog, header, the
 * Inertia <Form> (action/method/key derived from `editing`), and the Cancel /
 * submit footer. Callers supply only the fields as children. Pair with
 * `useResourceDialog`.
 */
export function ResourceFormDialog<T extends { id: number }>({
    open,
    onOpenChange,
    editing,
    entityLabel,
    baseUrl,
    onSuccess,
    description,
    contentClassName,
    children,
}: ResourceFormDialogProps<T>) {
    const isEdit = editing !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={contentClassName}>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? `Edit ${entityLabel}` : `New ${entityLabel}`}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? (description?.edit ??
                              `Update this ${entityLabel}.`)
                            : (description?.create ??
                              `Add a new ${entityLabel}.`)}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={editing ? editing.id : 'new'}
                    action={editing ? `${baseUrl}/${editing.id}` : baseUrl}
                    method={isEdit ? 'put' : 'post'}
                    disableWhileProcessing
                    onSuccess={(page) => {
                        onOpenChange(false);
                        onSuccess?.(page);
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {children({ processing, errors })}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Saving…
                                        </>
                                    ) : isEdit ? (
                                        'Save changes'
                                    ) : (
                                        `Create ${entityLabel}`
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
