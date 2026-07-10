<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Production-order lifecycle. `pending` is cancellable and can be completed;
 * `completed` (materials consumed, product produced) and `cancelled` are terminal.
 */
enum ProductionOrderStatus: string
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
