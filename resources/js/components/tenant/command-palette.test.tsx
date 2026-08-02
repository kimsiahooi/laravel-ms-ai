import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CommandPalette } from '@/components/tenant/command-palette';
import { renderPage } from '@/test/render';

// Nav items are gated by the signed-in user's permissions, so the palette only
// lists pages they can reach. Grant the catalog permissions the assertions touch.
const auth = {
    permissions: ['products.view', 'suppliers.view', 'categories.view'],
    is_admin: false,
};

describe('CommandPalette', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('opens from the trigger and lists workspace pages', async () => {
        renderPage(<CommandPalette />, {
            tenant: { slug: 'acme', name: 'Acme' },
            auth,
        });

        fireEvent.click(screen.getByRole('button', { name: /search pages/i }));

        // The dialog reveals grouped nav destinations.
        expect(await screen.findByText('Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Products')).toBeInTheDocument();
        expect(screen.getByText('Catalog')).toBeInTheDocument();
    });

    it('navigates to the selected page and closes', async () => {
        renderPage(<CommandPalette />, {
            tenant: { slug: 'acme', name: 'Acme' },
            auth,
        });

        fireEvent.click(screen.getByRole('button', { name: /search pages/i }));
        fireEvent.click(await screen.findByText('Products'));

        await waitFor(() =>
            expect(router.visit).toHaveBeenCalledWith('/acme/products'),
        );
    });
});
