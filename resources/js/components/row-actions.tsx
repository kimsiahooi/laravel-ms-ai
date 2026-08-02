import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RowActionsProps = {
    label: string;
    onEdit: () => void;
    onDelete: () => void;
    /** Hide Edit when false (e.g. the user lacks the update permission). */
    canEdit?: boolean;
    /** Hide Delete when false (e.g. the user lacks the delete permission). */
    canDelete?: boolean;
};

/**
 * The standard Edit / Delete row-actions dropdown used by every catalog table.
 * `label` names the row for the trigger's accessible label. `canEdit`/`canDelete`
 * (both default true) hide an action the user isn't allowed; when neither is
 * allowed the whole menu is omitted.
 */
export function RowActions({
    label,
    onEdit,
    onDelete,
    canEdit = true,
    canDelete = true,
}: RowActionsProps) {
    if (!canEdit && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={`Actions for ${label}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {canEdit ? (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
