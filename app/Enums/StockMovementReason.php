<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a stock movement happened. Backed by a short string stored in the
 * `stock_movements.reason` column. Manual movements use `Adjustment`; the other
 * cases are reserved for later phases (purchasing, sales, production, transfers).
 */
enum StockMovementReason: string
{
    case PurchaseReceipt = 'purchase_receipt';
    case SalesFulfillment = 'sales_fulfillment';
    case ProductionConsume = 'production_consume';
    case ProductionOutput = 'production_output';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';

    /** Humanized label for display (e.g. `purchase_receipt` => "Purchase receipt"). */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseReceipt => 'Purchase receipt',
            self::SalesFulfillment => 'Sales fulfillment',
            self::ProductionConsume => 'Production consume',
            self::ProductionOutput => 'Production output',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
            self::Adjustment => 'Adjustment',
        };
    }
}
