import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { NewResourceButton } from '@/components/new-resource-button';
import { TooltipProvider } from '@/components/ui/tooltip';

describe('NewResourceButton', () => {
    it('renders an enabled "New <label>" button and fires onClick', () => {
        const onClick = vi.fn();
        render(<NewResourceButton label="Order" onClick={onClick} />);

        const button = screen.getByRole('button', { name: 'New Order' });
        expect(button).toBeEnabled();

        fireEvent.click(button);
        expect(onClick).toHaveBeenCalledOnce();
    });

    it('disables the button when a prerequisite reason is given', () => {
        render(
            <TooltipProvider>
                <NewResourceButton
                    label="Order"
                    onClick={vi.fn()}
                    disabledReason="Add a supplier first"
                />
            </TooltipProvider>,
        );

        expect(
            screen.getByRole('button', { name: 'New Order' }),
        ).toBeDisabled();
    });
});
