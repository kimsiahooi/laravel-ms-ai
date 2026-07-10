<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sales-order lifecycle. `pending` is editable and can be fulfilled or cancelled;
 * `fulfilled` and `cancelled` are terminal.
 */
enum SalesOrderStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
        };
    }

    /** shadcn Badge variant for the status chip. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Fulfilled => 'default',
            self::Cancelled => 'outline',
        };
    }
}
