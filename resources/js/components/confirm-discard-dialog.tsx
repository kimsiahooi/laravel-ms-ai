import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

/**
 * A "Discard changes?" confirmation shown when a dirty form dialog is dismissed.
 * `onDiscard` runs when the user confirms (typically closing the parent dialog).
 */
export function ConfirmDiscardDialog({
    open,
    onOpenChange,
    onDiscard,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDiscard: () => void;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Discard changes?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You have unsaved changes. If you close now, they'll be
                        lost.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Keep editing</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() => {
                            onOpenChange(false);
                            onDiscard();
                        }}
                    >
                        Discard
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
