import { screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage } from '@/test/render';

const { default: TenantLogin } = await import('@/pages/tenant/login');

it('renders the tenant login form', () => {
    renderPage(<TenantLogin />, tenantProps());

    expect(
        screen.getByRole('button', { name: /sign in/i }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    // The password field (exact — /password/i also matches the "Show password" toggle).
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
});
