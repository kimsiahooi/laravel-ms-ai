import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ScanField } from '@/components/scan-field';

describe('ScanField', () => {
    it('fires onScan with the trimmed code on Enter, then clears', () => {
        const onScan = vi.fn();
        render(<ScanField onScan={onScan} />);

        const input = screen.getByRole('textbox', { name: /scan item/i });
        // A hardware scanner "types" the code then sends Enter.
        fireEvent.change(input, { target: { value: '  9551234567890  ' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(onScan).toHaveBeenCalledWith('9551234567890');
        expect((input as HTMLInputElement).value).toBe('');
    });

    it('ignores an empty scan', () => {
        const onScan = vi.fn();
        render(<ScanField onScan={onScan} />);

        fireEvent.keyDown(screen.getByRole('textbox', { name: /scan item/i }), {
            key: 'Enter',
        });

        expect(onScan).not.toHaveBeenCalled();
    });

    it('offers a camera scan button', () => {
        render(<ScanField onScan={vi.fn()} />);

        expect(
            screen.getByRole('button', { name: /scan with camera/i }),
        ).toBeInTheDocument();
    });
});
