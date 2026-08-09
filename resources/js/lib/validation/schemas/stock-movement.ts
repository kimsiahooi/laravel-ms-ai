import { z } from 'zod';
import {
    DECIMAL_MAX,
    DECIMAL_SCALE,
    decimalString,
    id,
    oneOf,
    optionalText,
} from '../primitives';

/**
 * Mirrors `StockMovementRequest` and `stock_movements.quantity` decimal(15,4).
 *
 * Moving nothing in or out would leave a meaningless row in a ledger that is never
 * deleted, but adjusting a level *to* zero is a real correction — so the lower
 * bound follows the type, exactly as the server's rule does.
 */
export const stockMovementSchema = z
    .object({
        warehouse_id: id('warehouse'),
        stockable: z
            .string()
            .min(1, 'The item field is required.')
            .regex(
                /^(product|raw_material):[0-9]+$/,
                'The selected item is invalid.',
            ),
        type: oneOf(['in', 'out', 'adjustment'], 'type'),
        quantity: z.string(),
        notes: optionalText(1000, 'notes'),
    })
    .superRefine((value, ctx) => {
        const result = decimalString({
            scale: DECIMAL_SCALE,
            max: DECIMAL_MAX,
            ...(value.type === 'adjustment' ? { min: 0 } : { gt: 0 }),
            label: 'quantity',
        }).safeParse(value.quantity);

        if (!result.success) {
            ctx.addIssue({
                code: 'custom',
                path: ['quantity'],
                message: result.error.issues[0].message,
            });
        }
    });
