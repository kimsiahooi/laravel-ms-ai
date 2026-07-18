import { screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { MultiComboboxField } from '@/components/multi-combobox-field';
import { renderPage } from '@/test/render';

const options = [
    { value: 'a', label: 'Alpha' },
    { value: 'b', label: 'Beta' },
    { value: 'c', label: 'Gamma' },
];

it('renders the label and the selected values as chips', () => {
    renderPage(
        <MultiComboboxField
            id="tags"
            label="Tags"
            options={options}
            value={['a', 'c']}
            onChange={() => {}}
        />,
        {},
    );

    expect(screen.getByText('Tags')).toBeInTheDocument();
    expect(screen.getByText('Alpha')).toBeInTheDocument();
    expect(screen.getByText('Gamma')).toBeInTheDocument();
    // Unselected options live only in the (closed) dropdown, not the trigger.
    expect(screen.queryByText('Beta')).not.toBeInTheDocument();
});

it('shows a placeholder when nothing is selected', () => {
    renderPage(
        <MultiComboboxField
            id="tags"
            label="Tags"
            options={options}
            value={[]}
            onChange={() => {}}
            placeholder="Pick some"
        />,
        {},
    );

    expect(screen.getByText('Pick some')).toBeInTheDocument();
});
