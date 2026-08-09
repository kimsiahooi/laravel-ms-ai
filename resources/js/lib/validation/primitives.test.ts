import { describe, expect, it } from 'vitest';
import {
    boolFlag,
    DECIMAL_MAX,
    email,
    exchangeRate,
    id,
    image,
    level,
    lines,
    money,
    oneOf,
    optionalEmail,
    optionalIsoDate,
    optionalText,
    percent,
    quantity,
    text,
} from './primitives';

/** The message of the first failure, or null when the value was accepted. */
function reject(
    schema: {
        safeParse: (v: unknown) => {
            success: boolean;
            error?: { issues: { message: string }[] };
        };
    },
    value: unknown,
): string | null {
    const result = schema.safeParse(value);

    return result.success ? null : (result.error?.issues[0]?.message ?? '');
}

describe('decimal fields', () => {
    it('accepts a value the column can hold exactly', () => {
        expect(reject(quantity(), '1.1234')).toBeNull();
        expect(reject(money(), '10.5')).toBeNull();
        expect(reject(money(), '0')).toBeNull();
        expect(reject(quantity(), String(DECIMAL_MAX))).toBeNull();
    });

    it('refuses more decimal places than the column stores', () => {
        // decimal(15,4) would keep 1.1235 — a different number from the one typed.
        expect(reject(money(), '1.12345')).toBe(
            'The price may not have more than 4 decimal places.',
        );
        expect(reject(quantity(), '1.00001')).toContain('4 decimal places');
    });

    it('refuses an amount that rounds away to nothing', () => {
        // Passes a "greater than zero" check but reaches the column as 0.0000.
        expect(reject(quantity(), '0.00004')).toContain('4 decimal places');
    });

    it('refuses more integer digits than the column holds', () => {
        expect(reject(quantity(), '100000000000')).toBe(
            'The quantity may not be greater than 99,999,999,999.',
        );
    });

    it('refuses exponent notation, which would be stored as a different number', () => {
        expect(reject(quantity(), '1e3')).toBe(
            'The quantity must be a number.',
        );
    });

    it('refuses text and blanks', () => {
        expect(reject(quantity(), 'abc')).toBe(
            'The quantity must be a number.',
        );
        expect(reject(quantity(), '')).toBe('The quantity field is required.');
        expect(reject(quantity(), '  ')).toBe(
            'The quantity field is required.',
        );
    });

    it('holds quantities above zero but lets prices and levels be zero', () => {
        expect(reject(quantity(), '0')).toBe(
            'The quantity must be greater than 0.',
        );
        expect(reject(quantity(), '-1')).toBe(
            'The quantity must be greater than 0.',
        );
        expect(reject(money(), '0')).toBeNull();
        expect(reject(level(), '0')).toBeNull();
        expect(reject(money(), '-0.01')).toBe('The price must be at least 0.');
    });

    it('allows an exchange rate the extra places its column has', () => {
        // exchange_rate is decimal(15,6), not (15,4) — six places are legitimate.
        expect(reject(exchangeRate(), '4.712346')).toBeNull();
        expect(reject(exchangeRate(), '4.7123456')).toContain(
            '6 decimal places',
        );
        expect(reject(exchangeRate(), '1000000000')).toContain(
            'may not be greater than 999,999,999',
        );
        expect(reject(exchangeRate(), '0')).toContain('greater than 0');
    });

    it('caps a percentage at 100', () => {
        expect(reject(percent(), '10')).toBeNull();
        expect(reject(percent(), '100')).toBeNull();
        expect(reject(percent(), '100.0001')).toContain(
            'may not be greater than 100',
        );
        expect(reject(percent(), '6.12345')).toContain('4 decimal places');
    });
});

describe('text fields', () => {
    it('accepts up to the column length and refuses one past it', () => {
        expect(reject(text(100, 'SKU'), 'A'.repeat(100))).toBeNull();
        expect(reject(text(100, 'SKU'), 'A'.repeat(101))).toBe(
            'The SKU may not be greater than 100 characters.',
        );
    });

    it('requires a value, whitespace not counting', () => {
        expect(reject(text(50, 'name'), '')).toBe(
            'The name field is required.',
        );
        expect(reject(text(50, 'name'), '   ')).toBe(
            'The name field is required.',
        );
    });

    it('lets an optional field be left blank but still caps its length', () => {
        expect(reject(optionalText(255, 'notes'), '')).toBeNull();
        expect(reject(optionalText(255, 'notes'), undefined)).toBeNull();
        expect(reject(optionalText(255, 'notes'), 'A'.repeat(256))).toContain(
            'may not be greater than 255 characters',
        );
    });
});

describe('email', () => {
    it('accepts an address and refuses a malformed one', () => {
        expect(reject(email(), 'ada@acme.test')).toBeNull();
        expect(reject(email(), 'ada@')).toBe(
            'The email must be a valid email address.',
        );
        expect(reject(email(), '')).toBe('The email field is required.');
    });

    it('lets an optional address be blank', () => {
        expect(reject(optionalEmail(), '')).toBeNull();
        expect(reject(optionalEmail(), 'nope')).toContain(
            'valid email address',
        );
    });
});

describe('identifiers and choices', () => {
    it('requires a selection and refuses a non-id', () => {
        expect(reject(id('customer'), '12')).toBeNull();
        expect(reject(id('customer'), '')).toBe(
            'The customer field is required.',
        );
        expect(reject(id('customer'), '5abc')).toBe(
            'The selected customer is invalid.',
        );
    });

    it('holds a choice to the list the server allows', () => {
        const currency = oneOf(['MYR', 'SGD'], 'currency');

        expect(reject(currency, 'MYR')).toBeNull();
        expect(reject(currency, 'GBP')).toBe(
            'The selected currency is invalid.',
        );
        // Case matters — the server's Rule::in is case-sensitive too.
        expect(reject(currency, 'myr')).toBe(
            'The selected currency is invalid.',
        );
    });

    it('accepts the hidden 1/0 a checkbox mirrors', () => {
        expect(reject(boolFlag(), '1')).toBeNull();
        expect(reject(boolFlag(), '0')).toBeNull();
        expect(reject(boolFlag(), undefined)).toBeNull();
        expect(reject(boolFlag(), 'yes')).not.toBeNull();
    });

    it('accepts a blank or real date', () => {
        expect(reject(optionalIsoDate('expected date'), '')).toBeNull();
        expect(
            reject(
                optionalIsoDate('expected date'),
                '2026-08-15T00:00:00.000Z',
            ),
        ).toBeNull();
        expect(reject(optionalIsoDate('expected date'), 'banana')).toBe(
            'The expected date is not a valid date.',
        );
    });
});

describe('line items', () => {
    const row = lines(quantity());

    it('needs at least one line, whether empty or missing entirely', () => {
        expect(reject(row, ['1'])).toBeNull();
        expect(reject(row, [])).toBe('Add at least one line item.');
        expect(reject(row, undefined)).toBe('Add at least one line item.');
    });

    it('refuses an unreasonable number of lines', () => {
        expect(
            reject(
                row,
                Array.from({ length: 201 }, () => '1'),
            ),
        ).toContain('may not have more than 200 line items');
    });
});

describe('image', () => {
    const png = (bytes: number, type = 'image/png') =>
        new File([new Uint8Array(bytes)], 'logo.png', { type });

    it('accepts a small image of an allowed type', () => {
        expect(reject(image(), png(1024))).toBeNull();
        expect(reject(image(), undefined)).toBeNull();
    });

    it('refuses an oversized file and a disallowed type', () => {
        expect(reject(image(1), png(2048))).toContain(
            'may not be greater than 1',
        );
        expect(reject(image(), png(10, 'application/pdf'))).toContain(
            'must be a file of type',
        );
    });
});
