import { screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import {
    type SettingsFieldSchema,
    SettingsForm,
} from '@/components/settings/settings-form';
import { renderPage } from '@/test/render';

const schema: SettingsFieldSchema[] = [
    {
        key: 'legal_name',
        type: 'text',
        label: 'Legal name',
        section: 'Company',
        description: 'Your registered company name.',
        options: [],
        placeholder: null,
        required: false,
    },
    {
        key: 'address',
        type: 'textarea',
        label: 'Address',
        section: 'Company',
        description: null,
        options: [],
        placeholder: null,
        required: false,
    },
    {
        key: 'tax_type',
        type: 'combobox',
        label: 'Tax type',
        section: 'Tax',
        description: 'The tax you charge.',
        options: [
            { value: 'sst', label: 'SST' },
            { value: 'gst', label: 'GST' },
        ],
        placeholder: null,
        required: false,
    },
];

it('renders each field type from the schema with its label + description', () => {
    renderPage(
        <SettingsForm
            category="business"
            tenantSlug="acme"
            schema={schema}
            values={{ legal_name: 'Acme', tax_type: 'gst' }}
        />,
        {},
    );

    // Sections become card titles.
    expect(screen.getByText('Company')).toBeInTheDocument();
    expect(screen.getByText('Tax')).toBeInTheDocument();

    // Text field: value bound, humanized description visible.
    expect(screen.getByLabelText('Legal name')).toHaveValue('Acme');
    expect(
        screen.getByText('Your registered company name.'),
    ).toBeInTheDocument();

    // Combobox field shows the selected label.
    expect(screen.getByRole('combobox')).toHaveTextContent('GST');

    expect(
        screen.getByRole('button', { name: /save changes/i }),
    ).toBeInTheDocument();
});
