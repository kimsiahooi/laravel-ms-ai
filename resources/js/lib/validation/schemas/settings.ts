import { z } from 'zod';
import { DECIMAL_MAX, decimalString } from '../primitives';

/** The limits a settings field carries, derived from its Laravel rules. */
export type FieldConstraints = {
    required: boolean;
    numeric: boolean;
    email: boolean;
    max: number | null;
    min: number | null;
    /** Decimal places the destination column keeps, when it is a decimal. */
    scale: number | null;
    in: string[];
};

type Field = {
    key: string;
    label: string;
    type: string;
    constraints: FieldConstraints;
};

/**
 * Settings are declared once in `app/Settings/BusinessSettings.php`, and the form
 * that edits them is generated from that declaration. This builds the matching
 * checks from the same payload, so the browser enforces exactly what the server
 * does without either being written out twice.
 */
export function settingsSchema(fields: Field[]) {
    const shape: Record<string, z.ZodTypeAny> = {};

    for (const field of fields) {
        const { constraints: rule, label } = field;
        const name = label.toLowerCase();
        let check: z.ZodTypeAny;

        if (rule.numeric) {
            check = decimalString({
                // A rate lands in a decimal column; without a scale it is a count.
                scale: rule.scale ?? 0,
                max: rule.max ?? DECIMAL_MAX,
                min: rule.min ?? 0,
                label: name,
            });
        } else if (rule.in.length > 0) {
            check = z.enum(rule.in as [string, ...string[]], {
                message: `The selected ${name} is invalid.`,
            });
        } else if (rule.email) {
            check = z
                .string()
                .email(`The ${name} must be a valid email address.`);
        } else {
            check = z.string();
        }

        if (!rule.numeric && rule.max !== null) {
            check = (check as z.ZodString).max(
                rule.max,
                `The ${name} may not be greater than ${rule.max} characters.`,
            );
        }

        // A file input sends a File (or nothing), and a toggle sends a boolean —
        // neither is a string, so leave those to the server.
        if (field.type === 'file' || field.type === 'toggle') {
            check = z.unknown();
        } else if (!rule.required) {
            // The browser always sends the key, so blank has to mean "not set".
            check = z.union([z.literal(''), check]).optional();
        } else if (!rule.numeric) {
            check = (check as z.ZodString).min(
                1,
                `The ${name} field is required.`,
            );
        }

        shape[field.key] = check;
    }

    return z.object(shape);
}
