import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Input } from '@/components/ui/input';
import { tenantProps } from '@/test/fixtures';
import { renderPage, renderPageWithForm } from '@/test/render';

type Supplier = { id: number };

function dialog() {
    return (
        <ResourceFormDialog<Supplier>
            open
            onOpenChange={() => {}}
            editing={null}
            entityLabel="supplier"
            baseUrl="/acme/suppliers"
        >
            {({ errors }) => (
                <div className="space-y-2">
                    <label htmlFor="name">Name</label>
                    <Input
                        id="name"
                        name="name"
                        aria-invalid={!!errors.name}
                        aria-describedby={
                            errors.name ? 'name-error' : undefined
                        }
                    />
                    <InputError id="name-error" message={errors.name} />
                </div>
            )}
        </ResourceFormDialog>
    );
}

describe('resource form dialog', () => {
    it('offers the create action with no errors showing', () => {
        renderPage(dialog(), tenantProps());

        expect(
            screen.getByRole('button', { name: 'Create supplier' }),
        ).toBeEnabled();
        expect(screen.getByLabelText('Name')).toHaveAttribute(
            'aria-invalid',
            'false',
        );
    });

    it('renders the server’s field error and marks the field invalid', () => {
        renderPageWithForm(dialog(), tenantProps(), {
            errors: { name: 'The name has already been taken.' },
        });

        expect(
            screen.getByText('The name has already been taken.'),
        ).toBeInTheDocument();
        const name = screen.getByLabelText('Name');
        expect(name).toHaveAttribute('aria-invalid', 'true');
        expect(name).toHaveAttribute('aria-describedby', 'name-error');
    });

    it('locks both buttons and says Saving… while the submit is in flight', () => {
        renderPageWithForm(dialog(), tenantProps(), { processing: true });

        expect(screen.getByText('Saving…')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
        // Cancel is disabled too, so a half-saved record can't be abandoned.
        expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
        expect(
            screen.queryByRole('button', { name: 'Create supplier' }),
        ).not.toBeInTheDocument();
    });
});
