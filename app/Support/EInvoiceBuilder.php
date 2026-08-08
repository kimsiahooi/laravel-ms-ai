<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\EInvoiceAddressData;
use App\Data\EInvoiceData;
use App\Data\EInvoiceExchangeRateData;
use App\Data\EInvoiceIdentificationData;
use App\Data\EInvoiceLineData;
use App\Data\EInvoicePartyData;
use App\Data\EInvoiceTaxSchemeData;
use App\Data\EInvoiceTaxSubtotalData;
use App\Data\InvoicePartyData;
use App\Data\SalesOrderData;
use App\Models\SalesOrder;
use App\Settings\BusinessSettings;

/**
 * Turns a sales order into the structured e-invoice payload {@see EInvoiceData}.
 *
 * Country adaptation lives here: the tenant's `country` picks the target standard
 * (MY → MyInvois, SG → InvoiceNow / PINT SG) and with it the invoice type code,
 * the tax category codes, and how the parties are identified. Everything else is
 * shared — both standards are UBL 2.1 underneath.
 *
 * The code values below are the sensible default for a goods seller; they are
 * emitted as opaque strings so a mapper can override without a schema change.
 */
class EInvoiceBuilder
{
    public const FORMAT = 'einvoice-hook/v1';

    /** MY: LHDN e-Invoice type code. SG: UNTDID 1001 commercial invoice. */
    private const INVOICE_TYPE_CODE = ['MY' => '01', 'SG' => '380'];

    /**
     * Tax category per country, keyed by the line's situation.
     *
     * MY (LHDN tax types): `01` Sales Tax — the goods tax a manufacturer charges;
     * `E` tax exemption; `06` Not Applicable when the tenant charges no tax.
     * SG (PINT SG GST categories): `SR` standard-rated; `ZR` zero-rated for an
     * untaxed line; `NG` non-GST-registered, the one category needing no rate.
     *
     * @var array<string, array<string, string>>
     */
    private const TAX_CATEGORY = [
        'MY' => ['taxed' => '01', 'untaxed' => 'E', 'no_tax' => '06'],
        'SG' => ['taxed' => 'SR', 'untaxed' => 'ZR', 'no_tax' => 'NG'],
    ];

    /** Peppol Electronic Address Scheme for a Singapore UEN. */
    private const SG_ENDPOINT_SCHEME = '0195';

    public function __construct(private readonly BusinessSettings $settings) {}

    public function build(SalesOrder $order): EInvoiceData
    {
        $values = $this->settings->values();
        // Settings constrain country to MY|SG; fall back to MY if it's ever neither.
        $country = (string) ($values['country'] ?? 'MY');
        $country = isset(self::INVOICE_TYPE_CODE[$country]) ? $country : 'MY';
        $baseCurrency = $this->settings->baseCurrency();
        $summary = SalesOrderData::fromSalesOrder($order, $baseCurrency);
        $taxRate = $summary->tax_rate;
        $charged = $taxRate > 0;

        $lines = $this->lines($order, $country, $taxRate, $charged, $summary->tax_amount);

        // The commercial date. MyInvois restamps IssueDate/IssueTime at submission.
        $issuedAt = ($order->fulfilled_at ?? $order->created_at)->utc();

        return new EInvoiceData(
            format: self::FORMAT,
            standard: $country === 'SG' ? 'invoicenow' : 'myinvois',
            country_code: $country,
            id: $order->number,
            invoice_type_code: self::INVOICE_TYPE_CODE[$country],
            issue_date: $issuedAt->format('Y-m-d'),
            issue_time: $issuedAt->format('H:i:s').'Z',
            document_currency_code: $order->currency,
            tax_currency_code: $baseCurrency,
            // Only meaningful when the invoice isn't already in the tax currency.
            tax_exchange_rate: $order->currency === $baseCurrency ? null : new EInvoiceExchangeRateData(
                source_currency_code: $order->currency,
                target_currency_code: $baseCurrency,
                calculation_rate: $summary->exchange_rate,
            ),
            accounting_supplier_party: $this->party(
                InvoicePartyData::fromSettings($this->settings),
                $country,
            ),
            accounting_customer_party: $order->customer !== null
                ? $this->party(InvoicePartyData::fromCustomer($order->customer), $country)
                : null,
            invoice_lines: $lines,
            tax_subtotals: $this->taxSubtotals($lines),
            // UBL restricts monetary amounts to 2 decimals, and PINT rule BR-S-08
            // compares a category's taxable amount against its lines — so every
            // amount is rounded, never a raw binary-float sum.
            line_extension_amount: round($summary->subtotal, 2),
            tax_exclusive_amount: round($summary->subtotal, 2),
            tax_amount: round($summary->tax_amount, 2),
            // The same tax expressed in the tax-accounting currency (Peppol IBT-111;
            // MyInvois's mandatory MYR TaxTotal). Without it a mapper reading
            // `tax_currency_code` alongside `tax_amount` would file the wrong figure.
            tax_amount_in_tax_currency: round($summary->tax_amount * $summary->exchange_rate, 2),
            tax_inclusive_amount: round($summary->total, 2),
            payable_amount: round($summary->total, 2),
        );
    }

    /**
     * The invoice lines. Per-line tax is rounded to the cent, then any residual
     * against the order's own tax figure is absorbed by the last taxed line — so
     * the lines always add up to the total the printed invoice shows.
     *
     * @return array<int, EInvoiceLineData>
     */
    private function lines(
        SalesOrder $order,
        string $country,
        float $taxRate,
        bool $charged,
        float $taxTotal,
    ): array {
        $categories = self::TAX_CATEGORY[$country];
        $lines = [];
        $lastTaxedIndex = null;
        $allocated = 0.0;

        foreach ($order->items as $index => $item) {
            $quantity = (float) $item->quantity;
            $price = (float) $item->unit_price;
            $amount = round($quantity * $price, 2);
            $taxed = $charged && $item->taxable;
            $tax = $taxed ? round($amount * $taxRate / 100, 2) : 0.0;

            if ($taxed) {
                $lastTaxedIndex = $index;
                $allocated += $tax;
            }

            $name = $item->product_snapshot['name'] ?? '—';

            $lines[] = new EInvoiceLineData(
                id: (string) ($index + 1),
                item_name: $name,
                item_description: $name,
                invoiced_quantity: $quantity,
                unit_code: $item->product_snapshot['unit'] ?? null,
                price_amount: $price,
                line_extension_amount: $amount,
                tax_category_code: match (true) {
                    ! $charged => $categories['no_tax'],
                    $taxed => $categories['taxed'],
                    default => $categories['untaxed'],
                },
                tax_percent: $taxed ? $taxRate : 0.0,
                tax_amount: $tax,
            );
        }

        $residual = round($taxTotal - $allocated, 2);
        if ($lastTaxedIndex !== null && $residual !== 0.0) {
            $line = $lines[$lastTaxedIndex];
            $lines[$lastTaxedIndex] = new EInvoiceLineData(
                id: $line->id,
                item_name: $line->item_name,
                item_description: $line->item_description,
                invoiced_quantity: $line->invoiced_quantity,
                unit_code: $line->unit_code,
                price_amount: $line->price_amount,
                line_extension_amount: $line->line_extension_amount,
                tax_category_code: $line->tax_category_code,
                tax_percent: $line->tax_percent,
                tax_amount: round($line->tax_amount + $residual, 2),
            );
        }

        return $lines;
    }

    /**
     * The lines rolled up per tax category — UBL's TaxSubtotal.
     *
     * @param  array<int, EInvoiceLineData>  $lines
     * @return array<int, EInvoiceTaxSubtotalData>
     */
    private function taxSubtotals(array $lines): array
    {
        $groups = [];
        foreach ($lines as $line) {
            $code = $line->tax_category_code;
            $groups[$code] ??= ['taxable' => 0.0, 'tax' => 0.0, 'percent' => $line->tax_percent];
            $groups[$code]['taxable'] += $line->line_extension_amount;
            $groups[$code]['tax'] += $line->tax_amount;
        }

        $subtotals = [];
        foreach ($groups as $code => $group) {
            $subtotals[] = new EInvoiceTaxSubtotalData(
                tax_category_code: (string) $code,
                taxable_amount: round($group['taxable'], 2),
                tax_amount: round($group['tax'], 2),
                percent: $group['percent'],
            );
        }

        return $subtotals;
    }

    /**
     * A party in the target standard's terms. MY lists TIN + registration (BRN)
     * + SST as scheme-qualified identifications; SG additionally carries the
     * Peppol endpoint that routes the document on the network.
     */
    private function party(InvoicePartyData $party, string $country): EInvoicePartyData
    {
        $identifications = [];
        if (filled($party->tin)) {
            $identifications[] = new EInvoiceIdentificationData('TIN', (string) $party->tin);
        }
        if (filled($party->registration_no)) {
            // MY qualifies a company registration as BRN; SG's equivalent is the UEN.
            $identifications[] = new EInvoiceIdentificationData(
                $country === 'SG' ? 'UEN' : 'BRN',
                (string) $party->registration_no,
            );
        }

        $taxSchemeId = $country === 'SG' ? 'GST' : 'SST';

        return new EInvoicePartyData(
            registration_name: $party->name,
            party_identifications: $identifications,
            // Peppol routes on the endpoint, which carries the UEN under scheme 0195.
            endpoint_id: $country === 'SG' && filled($party->registration_no)
                ? new EInvoiceIdentificationData(self::SG_ENDPOINT_SCHEME, 'SGUEN'.$party->registration_no)
                : null,
            party_tax_scheme: filled($party->sst_registration_no)
                ? new EInvoiceTaxSchemeData((string) $party->sst_registration_no, $taxSchemeId)
                : null,
            telephone: $party->phone,
            postal_address: new EInvoiceAddressData(
                address_line: $party->address,
                city_name: $party->city,
                postal_zone: $party->postcode,
                country_subentity_code: $party->state_code,
                // Left null when the party has no country on record. Defaulting it
                // to the tenant's own would silently turn an export into a domestic
                // sale; a blank field is the honest signal the checklist flags.
                country_identification_code: $party->country_code,
            ),
        );
    }
}
