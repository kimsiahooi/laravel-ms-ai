import { screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { Combobox } from '@/components/combobox';
import { renderPage } from '@/test/render';

// Regression guard for the ComboboxBase extraction: the single-select Combobox must
// still render its trigger + selection exactly as before.
const options = [
    { value: 'sst', label: 'SST (Malaysia)' },
    { value: 'gst', label: 'GST (Singapore)' },
];

it('shows the selected option label in the trigger', () => {
    renderPage(
        <Combobox options={options} value="gst" onChange={() => {}} />,
        {},
    );

    expect(screen.getByRole('combobox')).toHaveTextContent('GST (Singapore)');
});

it('shows the placeholder when nothing is selected', () => {
    renderPage(
        <Combobox
            options={options}
            value=""
            onChange={() => {}}
            placeholder="Choose tax"
        />,
        {},
    );

    expect(screen.getByRole('combobox')).toHaveTextContent('Choose tax');
});
