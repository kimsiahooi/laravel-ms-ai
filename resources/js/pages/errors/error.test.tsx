import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from '@/test/render';

const { default: ErrorPage } = await import('@/pages/errors/error');

describe('error page', () => {
    it('renders a friendly 403 that links back to the dashboard', () => {
        renderPage(
            <ErrorPage
                status={403}
                homeUrl="/acme/dashboard"
                homeLabel="Back to dashboard"
            />,
            {},
        );

        expect(
            screen.getByRole('heading', { name: /don't have access/i }),
        ).toBeInTheDocument();
        expect(screen.getByText('403')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /back to dashboard/i }),
        ).toHaveAttribute('href', '/acme/dashboard');
    });

    it('uses the provided home link outside a workspace', () => {
        renderPage(
            <ErrorPage status={404} homeUrl="/" homeLabel="Back to home" />,
            {},
        );

        expect(
            screen.getByRole('heading', { name: /couldn't find/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /back to home/i }),
        ).toHaveAttribute('href', '/');
    });
});
