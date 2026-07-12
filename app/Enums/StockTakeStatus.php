<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stock-take lifecycle. `draft` is editable and can be posted or cancelled;
 * `posted` (adjustments applied) and `cancelled` are terminal.
 */
enum StockTakeStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Cancelled => 'Cancelled',
        };
    }

    /** shadcn Badge variant for the status chip. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Posted => 'default',
            self::Cancelled => 'outline',
        };
    }
}
