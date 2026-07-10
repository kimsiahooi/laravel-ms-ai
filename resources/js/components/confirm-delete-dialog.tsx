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
};

/**
 * The standard destructive confirm dialog for deleting a resource record. Pair
 * with `useDelete` (`item={deleting}`, `onConfirm={confirm}`).
 */
export function ConfirmDeleteDialog<T>({
    item,
    onOpenChange,
    onConfirm,
    title,
    description,
}: ConfirmDeleteDialogProps<T>) {
    return (
        <Dialog open={item !== null} onOpenChange={onOpenChange}>
            <DialogContent>
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
                        <Trash2 className="size-4" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
