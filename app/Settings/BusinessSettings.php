<?php

declare(strict_types=1);

namespace App\Settings;

use App\Data\BusinessSettingsData;

/**
 * The tenant's company profile — the first settings category. Fields are declared once
 * here (type, options, default, validation, section); the dynamic form, the validation
 * rules, the stored values, and the document header all derive from this list.
 */
class BusinessSettings extends SettingsCategory
{
    public const CATEGORY = 'business';

    private const COMPANY = 'Company';

    private const CONTACT = 'Contact';

    private const TAX = 'Tax';

    private const FINANCIALS = 'Financials & numbering';

    /** @var list<string> */
    private const CURRENCIES = ['MYR', 'SGD', 'USD', 'EUR', 'CNY'];

    public function key(): string
    {
        return self::CATEGORY;
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return [
            new Field(
                key: 'legal_name',
                type: FieldType::Text,
                label: 'Legal name',
                section: self::COMPANY,
                description: 'Your registered company name, shown at the top of every document. Leave blank to use the workspace name.',
                placeholder: 'Acme Manufacturing Sdn Bhd',
                rules: ['nullable', 'string', 'max:255'],
            ),
            new Field(
                key: 'registration_no',
                type: FieldType::Text,
                label: 'Registration no.',
                section: self::COMPANY,
                description: 'Your SSM (Malaysia) or ACRA (Singapore) company registration number.',
                placeholder: 'SSM / ACRA number',
                rules: ['nullable', 'string', 'max:100'],
            ),
            new Field(
                key: 'logo',
                type: FieldType::File,
                label: 'Logo',
                section: self::COMPANY,
                default: false,
                description: 'Appears on invoices and printed documents. PNG or JPG, up to 2 MB.',
                rules: ['nullable', 'image', 'max:2048'],
            ),

            new Field(
                key: 'email',
                type: FieldType::Email,
                label: 'Email',
                section: self::CONTACT,
                description: 'Where customers and suppliers can reach your accounts team.',
                placeholder: 'billing@company.com',
                rules: ['nullable', 'email', 'max:255'],
            ),
            new Field(
                key: 'phone',
                type: FieldType::Text,
                label: 'Phone',
                section: self::CONTACT,
                description: 'A contact number shown on your documents.',
                placeholder: '+60 3-1234 5678',
                rules: ['nullable', 'string', 'max:50'],
            ),
            new Field(
                key: 'address',
                type: FieldType::Textarea,
                label: 'Address',
                section: self::CONTACT,
                description: 'Your business address, printed on invoices and delivery orders.',
                placeholder: 'Street, city, postcode',
                rules: ['nullable', 'string', 'max:1000'],
            ),

            new Field(
                key: 'country',
                type: FieldType::Combobox,
                label: 'Country',
                section: self::TAX,
                default: 'MY',
                description: 'Where your business is registered — sets the default tax treatment.',
                options: [
                    ['value' => 'MY', 'label' => 'Malaysia'],
                    ['value' => 'SG', 'label' => 'Singapore'],
                ],
                rules: ['required', 'string', 'in:MY,SG'],
            ),
            new Field(
                key: 'tax_type',
                type: FieldType::Combobox,
                label: 'Tax type',
                section: self::TAX,
                default: 'sst',
                description: 'The tax you charge: SST in Malaysia, GST in Singapore, or none.',
                options: [
                    ['value' => 'sst', 'label' => 'SST (Malaysia)'],
                    ['value' => 'gst', 'label' => 'GST (Singapore)'],
                    ['value' => 'none', 'label' => 'None'],
                ],
                rules: ['required', 'string', 'in:sst,gst,none'],
            ),
            new Field(
                key: 'tax_registration_no',
                type: FieldType::Text,
                label: 'Tax registration no.',
                section: self::TAX,
                description: 'Your SST or GST registration number, printed on tax invoices.',
                placeholder: 'SST / GST number',
                rules: ['nullable', 'string', 'max:100'],
            ),
            new Field(
                key: 'tin',
                type: FieldType::Text,
                label: 'TIN',
                section: self::TAX,
                description: 'Your Tax Identification Number — required later for MyInvois e-invoicing.',
                rules: ['nullable', 'string', 'max:100'],
            ),

            new Field(
                key: 'default_currency',
                type: FieldType::Combobox,
                label: 'Base currency',
                section: self::FINANCIALS,
                default: 'MYR',
                description: 'The currency your reports and totals roll up to.',
                options: array_map(
                    fn (string $code): array => ['value' => $code, 'label' => $code],
                    self::CURRENCIES,
                ),
                // Constrain to the offered set (same list as the options) so the value
                // can never drift outside what the picker shows.
                rules: ['required', 'string', 'in:'.implode(',', self::CURRENCIES)],
            ),
            new Field(
                key: 'financial_year_start_month',
                type: FieldType::Combobox,
                label: 'Financial year starts',
                section: self::FINANCIALS,
                default: '1',
                description: 'The month your financial year begins — used for reporting periods and number resets.',
                options: $this->monthOptions(),
                rules: ['required', 'integer', 'between:1,12'],
            ),
            new Field(
                key: 'sales_order_prefix',
                type: FieldType::Text,
                label: 'Sales order prefix',
                section: self::FINANCIALS,
                default: 'SO',
                description: 'The prefix for sales order numbers, e.g. SO-2026-0001.',
                rules: ['required', 'string', 'max:12'],
            ),
            new Field(
                key: 'purchase_order_prefix',
                type: FieldType::Text,
                label: 'Purchase order prefix',
                section: self::FINANCIALS,
                default: 'PO',
                description: 'The prefix for purchase order numbers, e.g. PO-2026-0001.',
                rules: ['required', 'string', 'max:12'],
            ),
            new Field(
                key: 'invoice_prefix',
                type: FieldType::Text,
                label: 'Invoice prefix',
                section: self::FINANCIALS,
                default: 'INV',
                description: 'The prefix for invoice numbers, e.g. INV-2026-0001.',
                rules: ['required', 'string', 'max:12'],
            ),
            new Field(
                key: 'number_reset',
                type: FieldType::Combobox,
                label: 'Numbering resets',
                section: self::FINANCIALS,
                default: 'yearly',
                description: 'Whether document numbering restarts each financial year.',
                options: [
                    ['value' => 'yearly', 'label' => 'Reset every year'],
                    ['value' => 'never', 'label' => 'Never reset'],
                ],
                rules: ['required', 'string', 'in:yearly,never'],
            ),
        ];
    }

    /** The typed subset used for document headers (shared to every tenant page). */
    public function documentHeader(): BusinessSettingsData
    {
        $values = $this->values();

        return new BusinessSettingsData(
            legal_name: $values['legal_name'] ?? null,
            registration_no: $values['registration_no'] ?? null,
            address: $values['address'] ?? null,
            tax_type: (string) ($values['tax_type'] ?? 'none'),
            tax_registration_no: $values['tax_registration_no'] ?? null,
            has_logo: (bool) ($values['logo'] ?? false),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        return array_map(
            fn (int $index): array => [
                'value' => (string) ($index + 1),
                'label' => $months[$index],
            ],
            range(0, 11),
        );
    }
}
