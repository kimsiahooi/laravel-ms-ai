import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { HowItWorks } from '@/components/how-it-works';

const steps = [
    { title: 'Step one', description: 'Do the first thing' },
    { title: 'Step two', description: 'Then the second thing' },
];

describe('HowItWorks', () => {
    it('renders the default title and every step when open', () => {
        render(<HowItWorks steps={steps} defaultOpen />);

        expect(screen.getByText('How this works')).toBeInTheDocument();
        expect(screen.getByText('Step one')).toBeInTheDocument();
        expect(screen.getByText('Then the second thing')).toBeInTheDocument();
    });

    it('honours a custom title', () => {
        render(
            <HowItWorks
                title="How production works"
                steps={steps}
                defaultOpen
            />,
        );

        expect(screen.getByText('How production works')).toBeInTheDocument();
    });
});
