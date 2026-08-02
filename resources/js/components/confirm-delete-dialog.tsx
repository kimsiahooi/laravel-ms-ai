import type { LucideIcon } from 'lucide-react';
import { Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDeleteDialogProps<T> = {
    /** The record pending deletion; the dialog is open while this is non-null. */
    item: T | null;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    title: string;
    description: ReactNode;
    /** Confirm-button label (default "Delete") — e.g. "Deactivate" for a reversible action. */
    confirmLabel?: string;
    /** Confirm-button icon (default Trash2). */
    confirmIcon?: LucideIcon;
};

/**
 * The standard destructive confirm dialog for deleting a resource record. Pair
 * with `useDelete` (`item={deleting}`, `onConfirm={confirm}`). The confirm label
 * and icon can be overridden for reversible destructive actions (e.g. deactivate).
 */
export function ConfirmDeleteDialog<T>({
    item,
    onOpenChange,
    onConfirm,
    title,
    description,
    confirmLabel = 'Delete',
    confirmIcon: ConfirmIcon = Trash2,
}: ConfirmDeleteDialogProps<T>) {
    return (
        <Dialog open={item !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                    >
                        <ConfirmIcon className="size-4" />
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
