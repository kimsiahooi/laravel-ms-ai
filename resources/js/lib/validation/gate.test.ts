import { describe, expect, it, vi } from 'vitest';
import { z } from 'zod';
import { dotPathToInputName, FORM_ERROR_KEY, runGate, zodErrors } from './gate';
import { id, lines, money, quantity, text } from './primitives';

const orderSchema = z.object({
    customer_id: id('customer'),
    notes: text(50, 'notes'),
    items: lines(z.object({ quantity: quantity(), unit_price: money() })),
});

const validOrder = {
    customer_id: '1',
    notes: 'ok',
    items: [{ quantity: '2', unit_price: '10.50' }],
};

describe('zodErrors', () => {
    it('returns null when everything passed', () => {
        expect(zodErrors(orderSchema, validOrder)).toBeNull();
    });

    it('keys a line-item failure exactly the way the server would', () => {
        const errors = zodErrors(orderSchema, {
            ...validOrder,
            items: [
                { quantity: '2', unit_price: '10.50' },
                { quantity: '1.12345', unit_price: '1' },
            ],
        });

        // Laravel reports this as items.1.quantity, and so must we — the same
        // <InputError> renders whichever of the two arrives.
        expect(Object.keys(errors ?? {})).toEqual(['items.1.quantity']);
    });

    it('keeps only the first failure per field', () => {
        const errors = zodErrors(orderSchema, {
            ...validOrder,
            customer_id: '',
        });

        expect(errors?.customer_id).toBe('The customer field is required.');
    });

    it('files a whole-form rule under its own key', () => {
        const schema = z
            .object({ from: z.string(), to: z.string() })
            .refine((value) => value.from !== value.to, {
                message: 'The destination must be a different warehouse.',
            });

        const errors = zodErrors(schema, { from: '1', to: '1' });

        expect(errors).toEqual({
            [FORM_ERROR_KEY]: 'The destination must be a different warehouse.',
        });
    });
});

describe('dotPathToInputName', () => {
    it('maps a dot path back to the name its input carries', () => {
        expect(dotPathToInputName('name')).toBe('name');
        expect(dotPathToInputName('items.0.quantity')).toBe(
            'items[0][quantity]',
        );
    });
});

describe('runGate', () => {
    const bag = () => ({ setError: vi.fn(), clearErrors: vi.fn() });

    it('lets a valid payload through and clears any stale messages', () => {
        const form = bag();

        expect(runGate(orderSchema, validOrder, form)).toBe(true);
        expect(form.clearErrors).toHaveBeenCalled();
        expect(form.setError).not.toHaveBeenCalled();
    });

    it('stops an invalid payload and publishes the messages', () => {
        const form = bag();

        expect(runGate(orderSchema, { ...validOrder, notes: '' }, form)).toBe(
            false,
        );
        expect(form.setError).toHaveBeenCalledWith({
            notes: 'The notes field is required.',
        });
    });

    it('clears the previous failure before reporting the new one', () => {
        const form = bag();

        runGate(orderSchema, { ...validOrder, notes: '' }, form);

        // Without this, a field the user has since fixed keeps its old message.
        expect(form.clearErrors.mock.invocationCallOrder[0]).toBeLessThan(
            form.setError.mock.invocationCallOrder[0],
        );
    });

    it('submits rather than trapping the user when no form bag is attached', () => {
        expect(runGate(orderSchema, { nonsense: true }, null)).toBe(true);
    });
});
