<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Purchase-order lifecycle. `pending` is editable and can be received or cancelled;
 * `received` and `cancelled` are terminal.
 */
enum PurchaseOrderStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /** shadcn Badge variant for the status chip. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Received => 'default',
            self::Cancelled => 'outline',
        };
    }
}
