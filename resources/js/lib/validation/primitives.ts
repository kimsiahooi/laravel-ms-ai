import { z } from 'zod';

/**
 * The database's constraints, written down once.
 *
 * Every limit here comes from a column definition, because the column is what
 * ultimately accepts or mangles the value. MySQL runs in strict mode, which is
 * loud about some violations and silent about the worst one:
 *
 * - too many integer digits  → error 1264 → a 500
 * - a string past its length → error 1406 → a 500
 * - **too many decimal places → silently rounded**
 *
 * That last case is why these exist. `unit_price=1.12345` on a `decimal(15,4)`
 * column is accepted by `numeric` and stored as `1.1235`, so the saved invoice
 * quietly disagrees with what the user typed. Nothing in the app noticed.
 *
 * Values arrive from the DOM as strings, so scale is checked with a regex **on the
 * string**. Parsing to a float first would launder the very bug being caught.
 *
 * Each Laravel FormRequest carries the same limits, so a request made outside the
 * browser is refused identically.
 */

/** `decimal(15,4)` holds 11 integer digits. Mirrors `TenantFormRequest::DECIMAL_MAX`. */
export const DECIMAL_MAX = 99_999_999_999;

/** `decimal(15,6)` (exchange_rate) holds 9 integer digits. */
export const RATE_MAX = 999_999_999;

/** Decimal places on the `decimal(15,4)` money/quantity columns. */
export const DECIMAL_SCALE = 4;

/** Decimal places on the `decimal(15,6)` exchange-rate columns. */
export const RATE_SCALE = 6;

/**
 * A plain decimal, the way the database writes one. Exponent notation is refused
 * on purpose: `numeric` accepts `1e3` and MySQL would store 1000, which is not
 * what anyone typed.
 */
const DECIMAL_PATTERN = /^-?\d+(\.\d+)?$/;

/** How many digits sit after the decimal point. */
function decimalPlaces(value: string): number {
    return value.split('.')[1]?.length ?? 0;
}

type DecimalOptions = {
    /** Decimal places the column stores; more than this would be rounded away. */
    scale: number;
    /** Largest value the column's integer digits can hold. */
    max: number;
    min?: number;
    gt?: number;
    label: string;
};

/**
 * A numeric field that reaches us as a string. Every check is expressed against
 * the raw text, and the messages match Laravel's wording so a value refused here
 * reads the same as one refused by the server.
 */
export function decimalString({ scale, max, min, gt, label }: DecimalOptions) {
    const numeric = (value: string) => DECIMAL_PATTERN.test(value);

    return (
        z
            .string()
            .trim()
            .min(1, `The ${label} field is required.`)
            .refine(numeric, `The ${label} must be a number.`)
            // The silent-rounding guard.
            .refine(
                (value) => !numeric(value) || decimalPlaces(value) <= scale,
                `The ${label} may not have more than ${scale} decimal places.`,
            )
            .refine(
                (value) => !numeric(value) || Number(value) <= max,
                `The ${label} may not be greater than ${max.toLocaleString('en-US')}.`,
            )
            .refine(
                (value) =>
                    !numeric(value) || gt === undefined || Number(value) > gt,
                `The ${label} must be greater than ${gt}.`,
            )
            .refine(
                (value) =>
                    !numeric(value) ||
                    min === undefined ||
                    Number(value) >= min,
                `The ${label} must be at least ${min}.`,
            )
    );
}

/** A counted amount — `decimal(15,4)`, always more than nothing. */
export const quantity = (label = 'quantity') =>
    decimalString({ scale: DECIMAL_SCALE, max: DECIMAL_MAX, gt: 0, label });

/** A price or cost — `decimal(15,4)`. Zero is allowed (a free line is legitimate). */
export const money = (label = 'price') =>
    decimalString({ scale: DECIMAL_SCALE, max: DECIMAL_MAX, min: 0, label });

/** A stock level or count — `decimal(15,4)`, and zero is a real answer. */
export const level = (label = 'level') =>
    decimalString({ scale: DECIMAL_SCALE, max: DECIMAL_MAX, min: 0, label });

/** `decimal(15,6)` — six places, not four. */
export const exchangeRate = (label = 'exchange rate') =>
    decimalString({ scale: RATE_SCALE, max: RATE_MAX, gt: 0, label });

/** A percentage — `decimal(8,4)`, capped at 100 by the business rule. */
export const percent = (label = 'rate') =>
    decimalString({ scale: DECIMAL_SCALE, max: 100, min: 0, label });

/** A required `varchar(max)`. */
export const text = (max: number, label: string) =>
    z
        .string()
        .trim()
        .min(1, `The ${label} field is required.`)
        .max(max, `The ${label} may not be greater than ${max} characters.`);

/**
 * An optional `varchar(max)`. The browser always sends the key, so an empty string
 * has to pass — "left blank" is not "invalid".
 */
export const optionalText = (max: number, label: string) =>
    z
        .string()
        .trim()
        .max(max, `The ${label} may not be greater than ${max} characters.`)
        .optional();

/** An optional email address, length-capped like its column. */
export const optionalEmail = (max = 255, label = 'email') =>
    z
        .union([
            z.literal(''),
            z
                .string()
                .email(`The ${label} must be a valid email address.`)
                .max(
                    max,
                    `The ${label} may not be greater than ${max} characters.`,
                ),
        ])
        .optional();

/** A required email address. */
export const email = (max = 255, label = 'email') =>
    z
        .string()
        .trim()
        .min(1, `The ${label} field is required.`)
        .email(`The ${label} must be a valid email address.`)
        .max(max, `The ${label} may not be greater than ${max} characters.`);

/**
 * A required foreign key, as a picker's hidden input sends it. Whether the row
 * exists is the server's business — this only rejects blanks and non-ids.
 */
export const id = (label: string) =>
    z
        .string()
        .trim()
        .min(1, `The ${label} field is required.`)
        .regex(/^\d+$/, `The selected ${label} is invalid.`);

/** An optional foreign key. */
export const optionalId = (label: string) =>
    z
        .union([
            z.literal(''),
            z.string().regex(/^\d+$/, `The selected ${label} is invalid.`),
        ])
        .optional();

/** One of a fixed set — the same list the server checks with `Rule::in`. */
export const oneOf = (values: readonly string[], label: string) =>
    z.enum(values as [string, ...string[]], {
        message: `The selected ${label} is invalid.`,
    });

/** A hidden `1`/`0` checkbox mirror. */
export const boolFlag = () => z.enum(['0', '1']).optional();

/** An optional date, sent as an ISO string by a hidden input. */
export const optionalIsoDate = (label: string) =>
    z
        .union([
            z.literal(''),
            z
                .string()
                .refine(
                    (value) => !Number.isNaN(Date.parse(value)),
                    `The ${label} is not a valid date.`,
                ),
        ])
        .optional();

/**
 * Repeatable line items. An order with no rows omits `items` entirely, so the
 * message has to make sense for "missing" as well as "empty".
 */
export const lines = <T extends z.ZodTypeAny>(
    row: T,
    { min = 1, max = 200 }: { min?: number; max?: number } = {},
) =>
    z
        .array(row, { message: 'Add at least one line item.' })
        .min(min, 'Add at least one line item.')
        .max(max, `A document may not have more than ${max} line items.`);

/** An optional upload. Inertia drops a file input that was never touched. */
export const image = (maxKb = 2048) =>
    z
        .instanceof(File)
        .refine(
            (file) => file.size <= maxKb * 1024,
            `The image may not be greater than ${maxKb} kilobytes.`,
        )
        .refine(
            (file) =>
                ['image/jpeg', 'image/png', 'image/webp'].includes(file.type),
            'The image must be a file of type: jpg, jpeg, png, webp.',
        )
        .optional();
