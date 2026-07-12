<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Return lifecycle (purchase + sales returns). `pending` is editable and can be
 * completed or cancelled; `completed` (stock moved) and `cancelled` are terminal.
 */
enum ReturnStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** shadcn Badge variant for the status chip. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Completed => 'default',
            self::Cancelled => 'outline',
        };
    }
}
