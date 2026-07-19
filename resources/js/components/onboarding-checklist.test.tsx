import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    OnboardingChecklist,
    type OnboardingStep,
} from '@/components/onboarding-checklist';

const steps: OnboardingStep[] = [
    {
        title: 'Add a location',
        description: 'Create a site.',
        href: '/acme/locations',
        done: true,
    },
    {
        title: 'Add a warehouse',
        description: 'Somewhere to hold stock.',
        href: '/acme/warehouses',
        done: false,
    },
];

describe('OnboardingChecklist', () => {
    it('shows progress and every step while setup is incomplete', () => {
        render(<OnboardingChecklist steps={steps} />);

        expect(screen.getByText(/1 of 2 done/)).toBeInTheDocument();
        expect(screen.getByText('Add a location')).toBeInTheDocument();
        expect(screen.getByText('Add a warehouse')).toBeInTheDocument();
        // The first unmet step is flagged as next.
        expect(screen.getByText('Next')).toBeInTheDocument();
    });

    it('renders nothing once every step is done', () => {
        const { container } = render(
            <OnboardingChecklist
                steps={steps.map((step) => ({ ...step, done: true }))}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
