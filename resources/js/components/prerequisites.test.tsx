import { render, screen } from '@testing-library/react';
import { Package } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import {
    humanizeList,
    PrereqEmptyState,
    type Prerequisite,
    prerequisiteReason,
    unmetPrerequisites,
} from '@/components/prerequisites';

const prereqs: Prerequisite[] = [
    { label: 'a supplier', href: '/acme/suppliers', met: false },
    { label: 'a raw material', href: '/acme/raw-materials', met: true },
];

describe('prerequisite helpers', () => {
    it('humanizeList joins with commas and a trailing "and"', () => {
        expect(humanizeList([])).toBe('');
        expect(humanizeList(['a'])).toBe('a');
        expect(humanizeList(['a', 'b'])).toBe('a and b');
        expect(humanizeList(['a', 'b', 'c'])).toBe('a, b and c');
    });

    it('unmetPrerequisites keeps only the unmet ones', () => {
        expect(unmetPrerequisites(prereqs)).toEqual([prereqs[0]]);
    });

    it('prerequisiteReason reads as an "Add … first" nudge, or nothing', () => {
        expect(prerequisiteReason([])).toBeUndefined();
        expect(prerequisiteReason(unmetPrerequisites(prereqs))).toBe(
            'Add a supplier first',
        );
    });
});

describe('PrereqEmptyState', () => {
    it('names what is missing and links to each one', () => {
        render(
            <PrereqEmptyState
                icon={Package}
                entity="purchase order"
                missing={unmetPrerequisites(prereqs)}
            />,
        );

        expect(
            screen.getByText(/Before you can create a purchase order/),
        ).toBeInTheDocument();
        const link = screen.getByRole('link', { name: /Add a supplier/ });
        expect(link).toHaveAttribute('href', '/acme/suppliers');
    });
});
