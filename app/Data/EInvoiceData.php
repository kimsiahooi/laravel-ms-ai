<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A sales order as a structured e-invoice payload.
 *
 * This is an INTEGRATION HOOK, not a filed document. It is deliberately NOT a
 * certified MyInvois or PINT SG document: it carries the invoice data under UBL
 * 2.1 business-term names (the standard both MY and SG build on) so the
 * accounting package or Peppol access point can map it mechanically and submit.
 *
 * Fields this app doesn't capture are the submitting layer's to supply: MSIC
 * industry code + business activity, per-line classification codes, and the
 * digital signature. One gap it CANNOT fill is a MY tax-exemption reason for a
 * line coded `E` — that is seller business data the app never asks for.
 *
 * `standard` names the target the codes were resolved for, so a mapper never has
 * to guess whether `tax_category_code` is an LHDN tax type or a Peppol GST code.
 */
class EInvoiceData extends Data
{
    /**
     * @param  array<int, EInvoiceLineData>  $invoice_lines
     * @param  array<int, EInvoiceTaxSubtotalData>  $tax_subtotals
     */
    public function __construct(
        /** Payload contract version — bump when the shape changes. */
        public string $format,
        /** `myinvois` (MY) or `invoicenow` (SG), from the tenant's country. */
        public string $standard,
        public string $country_code,
        /** Document number (UBL Invoice/ID). Null on an order that predates numbering. */
        public ?string $id,
        /** MY `01` (Invoice) / SG `380` (commercial invoice). */
        public string $invoice_type_code,
        /**
         * The business issue date (fulfilment, else creation). NOTE: MyInvois
         * requires IssueDate/IssueTime to be the *submission* timestamp in UTC —
         * the submitting layer restamps them; this is the commercial date.
         */
        public string $issue_date,
        public string $issue_time,
        public string $document_currency_code,
        /** The tenant's base currency — what tax is accounted in. */
        public string $tax_currency_code,
        public ?EInvoiceExchangeRateData $tax_exchange_rate,
        public EInvoicePartyData $accounting_supplier_party,
        public ?EInvoicePartyData $accounting_customer_party,
        #[DataCollectionOf(EInvoiceLineData::class)]
        public array $invoice_lines,
        #[DataCollectionOf(EInvoiceTaxSubtotalData::class)]
        public array $tax_subtotals,
        /** Sum of line amounts, before tax (UBL LegalMonetaryTotal). */
        public float $line_extension_amount,
        public float $tax_exclusive_amount,
        /** Total tax, in `document_currency_code`. */
        public float $tax_amount,
        /** The same tax converted to `tax_currency_code` (Peppol IBT-111). */
        public float $tax_amount_in_tax_currency,
        public float $tax_inclusive_amount,
        public float $payable_amount,
    ) {}
}
