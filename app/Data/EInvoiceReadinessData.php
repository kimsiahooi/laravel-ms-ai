<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Settings\BusinessSettings;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether a sales order carries everything an e-invoice needs. The export is a
 * hook, not a filing — a not-ready order still exports, so the accounting package
 * / access point sees exactly which fields are blank. This is the checklist the
 * SO screen shows so the gaps get fixed before submission.
 */
#[TypeScript]
class EInvoiceReadinessData extends Data
{
    /**
     * @param  array<int, EInvoiceCheckData>  $checks
     */
    public function __construct(
        public bool $ready,
        /** How many checks pass, for the "3 of 6" progress line. */
        public int $passed,
        public int $total,
        #[DataCollectionOf(EInvoiceCheckData::class)]
        public array $checks,
    ) {}

    public static function forSalesOrder(SalesOrder $order, BusinessSettings $settings): self
    {
        $values = $settings->values();
        $seller = InvoicePartyData::fromSettings($settings);
        $buyer = $order->customer !== null ? InvoicePartyData::fromCustomer($order->customer) : null;
        $country = (string) ($values['country'] ?? 'MY');
        // MY identifies a business by BRN, SG by UEN — same column, different label.
        $registrationLabel = $country === 'SG' ? 'UEN' : 'registration no.';

        $taxType = (string) ($values['tax_type'] ?? 'none');
        $taxLabel = $taxType === 'gst' ? 'GST' : 'SST';

        $checks = [
            new EInvoiceCheckData(
                key: 'seller_identity',
                label: 'Your business name and address',
                passed: filled($seller->name) && filled($seller->address) && filled($seller->country_code),
                hint: 'Add your legal name, address and country in Business settings.',
            ),
            new EInvoiceCheckData(
                key: 'seller_tin',
                label: 'Your TIN',
                passed: filled($seller->tin),
                hint: 'Add your Tax Identification Number in Business settings.',
            ),
            new EInvoiceCheckData(
                key: 'seller_registration',
                label: "Your {$registrationLabel}",
                passed: filled($seller->registration_no),
                hint: "Add your business {$registrationLabel} in Business settings.",
            ),
            new EInvoiceCheckData(
                key: 'tax_type',
                label: 'Tax type set',
                passed: $taxType !== 'none',
                hint: 'Choose SST or GST in Business settings — an e-invoice needs a tax treatment.',
            ),
            new EInvoiceCheckData(
                key: 'tax_registration',
                label: "Your {$taxLabel} registration no.",
                // Only required once the tenant actually charges tax.
                passed: $taxType === 'none' || filled($seller->sst_registration_no),
                hint: "You charge {$taxLabel} but have no {$taxLabel} registration number — add it in Business settings.",
            ),
            new EInvoiceCheckData(
                key: 'buyer_tin',
                label: "Customer's TIN",
                passed: $buyer !== null && filled($buyer->tin),
                hint: "Add the customer's Tax Identification Number on their record.",
            ),
            new EInvoiceCheckData(
                key: 'buyer_registration',
                label: "Customer's {$registrationLabel}",
                passed: $buyer !== null && filled($buyer->registration_no),
                hint: "Add the customer's business {$registrationLabel} on their record.",
            ),
            new EInvoiceCheckData(
                key: 'buyer_address',
                label: "Customer's address",
                passed: $buyer !== null && filled($buyer->address) && filled($buyer->country_code),
                hint: "Add the customer's address and country on their record.",
            ),
            new EInvoiceCheckData(
                key: 'document_number',
                label: 'Document number',
                passed: filled($order->number),
                hint: 'This order has no document number — new orders are numbered automatically.',
            ),
            new EInvoiceCheckData(
                key: 'not_cancelled',
                label: 'Order not cancelled',
                // A cancelled sale must never be presented as a filable invoice.
                passed: $order->status !== SalesOrderStatus::Cancelled,
                hint: 'This order is cancelled — it is not a sale and must not be filed as an invoice.',
            ),
        ];

        $passed = count(array_filter($checks, fn (EInvoiceCheckData $check): bool => $check->passed));

        return new self(
            ready: $passed === count($checks),
            passed: $passed,
            total: count($checks),
            checks: $checks,
        );
    }
}
