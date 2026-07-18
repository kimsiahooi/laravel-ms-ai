import { screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { expect, it, vi } from 'vitest';
import type { SettingsFieldSchema } from '@/components/settings/settings-form';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: SettingsIndex } = await import(
    '@/pages/tenant/settings/index'
);

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
];

it('renders the business settings page from its schema', () => {
    renderPage(<SettingsIndex />, {
        ...tenantProps(),
        category: 'business',
        schema,
        values: { legal_name: 'Acme' },
    });

    expect(
        screen.getByRole('heading', { name: 'Business settings' }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Legal name')).toHaveValue('Acme');
    expect(
        screen.getByRole('button', { name: /save changes/i }),
    ).toBeInTheDocument();
});
