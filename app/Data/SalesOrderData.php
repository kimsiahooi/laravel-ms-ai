<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Settings\BusinessSettings;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A sales order flattened for the list + edit form. */
#[TypeScript]
class SalesOrderData extends Data
{
    /**
     * @param  array<int, SalesOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public ?string $customer,
        public ?int $customer_id,
        public string $status,
        public string $status_label,
        public string $currency,
        public float $exchange_rate,
        public string $base_currency,
        public ?string $number,
        public int $item_count,
        public float $total,
        public float $base_total,
        public ?string $notes,
        public ?string $expected_date,
        public ?string $fulfilled_at,
        public string $created_at,
        #[DataCollectionOf(SalesOrderItemData::class)]
        public array $items,
    ) {}

    public static function fromSalesOrder(SalesOrder $order, ?string $baseCurrency = null): self
    {
        $baseCurrency ??= app(BusinessSettings::class)->baseCurrency();

        $items = $order->items->map(
            fn (SalesOrderItem $item): SalesOrderItemData => SalesOrderItemData::from($item),
        );
        $total = (float) $items->sum(fn (SalesOrderItemData $item): float => $item->quantity * $item->unit_price);
        $exchangeRate = (float) $order->exchange_rate;

        return new self(
            id: $order->id,
            customer: $order->customer?->name,
            customer_id: $order->customer_id,
            status: $order->status->value,
            status_label: $order->status->label(),
            currency: $order->currency,
            exchange_rate: $exchangeRate,
            base_currency: $baseCurrency,
            number: $order->number,
            item_count: $items->count(),
            total: $total,
            // The order total expressed in the tenant base currency (= total when
            // the order is already in the base currency, i.e. rate 1).
            base_total: $total * $exchangeRate,
            notes: $order->notes,
            expected_date: $order->expected_date?->toISOString(),
            fulfilled_at: $order->fulfilled_at?->toISOString(),
            created_at: $order->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
