import { fireEvent, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { filters, paginator, tenantProps } from '@/test/fixtures';
import {
    renderPage,
    renderPageWithForm,
    submitForm,
    submittedVisits,
} from '@/test/render';

vi.mock('@/layouts/tenant-layout', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

const { default: CategoriesIndex } = await import('@/pages/tenant/categories');

function props(overrides: Record<string, unknown> = {}) {
    return {
        ...tenantProps(),
        categories: paginator([
            { id: 1, name: 'Electronics', description: 'Electronic parts' },
        ]),
        filters: filters(),
        ...overrides,
    };
}

describe('categories index', () => {
    it('renders the list with a row and the export control', () => {
        renderPage(<CategoriesIndex />, props());

        expect(
            screen.getByRole('heading', { level: 1, name: 'Categories' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Electronics')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Export' }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when there are no categories', () => {
        renderPage(<CategoriesIndex />, props({ categories: paginator([]) }));

        expect(screen.getByText(/no categories yet/i)).toBeInTheDocument();
    });

    it('shows a rejected field on the create form', async () => {
        renderPageWithForm(<CategoriesIndex />, props({}), {
            errors: { name: 'The name has already been taken.' },
        });

        fireEvent.click(screen.getByRole('button', { name: /^new /i }));

        expect(
            await screen.findByText('The name has already been taken.'),
        ).toBeInTheDocument();
        // Scoped to the rejected field: flagging the wrong input must not pass.
        expect(document.querySelector('#name')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });

    it('refuses to send a create the server would reject, and says why (zod)', async () => {
        // The real <Form> — renderPageWithForm swaps it for a stub, so no gate runs.
        renderPage(<CategoriesIndex />, props({}));

        fireEvent.click(screen.getByRole('button', { name: /^new /i }));
        const name = await screen.findByLabelText('Name');
        submitForm(name);

        // Nothing left the browser…
        expect(submittedVisits()).not.toHaveBeenCalled();
        // …and the reason is under the field, not in a toast.
        expect(name).toHaveAttribute('aria-invalid', 'true');
        expect(
            document.getElementById(
                name.getAttribute('aria-describedby') ?? '',
            ),
        ).toHaveTextContent('The name field is required.');
    });

    it('refuses a name longer than the column holds (zod)', async () => {
        renderPage(<CategoriesIndex />, props({}));

        fireEvent.click(screen.getByRole('button', { name: /^new /i }));
        const name = await screen.findByLabelText('Name');
        fireEvent.change(name, { target: { value: 'A'.repeat(256) } });
        submitForm(name);

        expect(submittedVisits()).not.toHaveBeenCalled();
        expect(
            document.getElementById(
                name.getAttribute('aria-describedby') ?? '',
            ),
        ).toHaveTextContent('may not be greater than 255 characters');
    });

    it('sends the create once every field is acceptable', async () => {
        renderPage(<CategoriesIndex />, props({}));

        fireEvent.click(screen.getByRole('button', { name: /^new /i }));
        const name = await screen.findByLabelText('Name');
        fireEvent.change(name, { target: { value: 'Fasteners' } });
        submitForm(name);

        expect(submittedVisits()).toHaveBeenCalledTimes(1);
        expect(submittedVisits().mock.calls[0][1]).toMatchObject({
            name: 'Fasteners',
        });
    });
});
