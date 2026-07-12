import { render, screen } from '@testing-library/react';
import { Package } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { StatCard } from '@/components/stat-card';

describe('StatCard', () => {
    it('renders the label, value and sub-line', () => {
        render(
            <StatCard
                icon={Package}
                label="Sales"
                value="1,200"
                sub="12 orders"
            />,
        );

        expect(screen.getByText('Sales')).toBeInTheDocument();
        expect(screen.getByText('1,200')).toBeInTheDocument();
        expect(screen.getByText('12 orders')).toBeInTheDocument();
    });

    it('omits the sub-line when none is given', () => {
        const { container } = render(
            <StatCard icon={Package} label="Purchases" value={0} />,
        );

        expect(screen.getByText('Purchases')).toBeInTheDocument();
        expect(screen.getByText('0')).toBeInTheDocument();
        // Only label + value paragraphs — no third (sub) line.
        expect(container.querySelectorAll('p')).toHaveLength(2);
    });
});
