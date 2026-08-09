import { screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { tenantProps } from '@/test/fixtures';
import { renderPage, renderPageWithForm } from '@/test/render';

const { default: TenantLogin } = await import('@/pages/tenant/login');

it('renders the tenant login form, posting to this tenant', () => {
    const { container } = renderPage(<TenantLogin />, tenantProps());

    expect(
        screen.getByRole('button', { name: /sign in/i }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    // The password field (exact — /password/i also matches the "Show password" toggle).
    expect(screen.getByLabelText('Password')).toBeInTheDocument();

    // Credentials must go to *this* tenant's endpoint — a dropped slug would
    // authenticate against whichever tenant the path resolved to instead.
    const form = container.querySelector('form');
    expect(form).toHaveAttribute('action', '/acme/login');
    expect(form).toHaveAttribute('method', 'post');
});

it('shows why sign-in was rejected and points a screen reader at the field', () => {
    renderPageWithForm(<TenantLogin />, tenantProps(), {
        errors: { email: 'These credentials do not match our records.' },
    });

    expect(screen.getByRole('alert')).toHaveTextContent(
        /do not match our records/i,
    );
    // The message is announced *and* tied to the input it belongs to, not just drawn.
    const email = screen.getByLabelText(/email/i);
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveAttribute('aria-describedby', 'email-error');
});

it('marks only the field the server rejected', () => {
    renderPageWithForm(<TenantLogin />, tenantProps(), {
        errors: { password: 'The password field is required.' },
    });

    expect(screen.getByLabelText('Password')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    expect(screen.getByLabelText(/email/i)).toHaveAttribute(
        'aria-invalid',
        'false',
    );
});

it('disables sign-in while submitting, without going silent', () => {
    renderPageWithForm(<TenantLogin />, tenantProps(), { processing: true });

    // Still named — a spinner with no accessible name leaves a screen-reader user
    // with no idea the press registered.
    const submit = screen.getByRole('button', { name: /signing in/i });

    expect(submit).toBeDisabled();
    expect(submit).toHaveAttribute('type', 'submit');
});
