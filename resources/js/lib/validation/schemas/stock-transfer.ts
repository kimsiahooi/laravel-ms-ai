import { z } from 'zod';
import { id, optionalText, quantity } from '../primitives';

/** Mirrors `StockTransferRequest` and `stock_transfers.quantity` decimal(15,4). */
export const stockTransferSchema = z
    .object({
        stockable: z
            .string()
            .min(1, 'The item field is required.')
            .regex(
                /^(product|raw_material):[0-9]+$/,
                'The selected item is invalid.',
            ),
        from_warehouse_id: id('source warehouse'),
        to_warehouse_id: id('destination warehouse'),
        quantity: quantity(),
        notes: optionalText(1000, 'notes'),
    })
    .refine((value) => value.from_warehouse_id !== value.to_warehouse_id, {
        path: ['to_warehouse_id'],
        message: 'The destination must be a different warehouse.',
    });
