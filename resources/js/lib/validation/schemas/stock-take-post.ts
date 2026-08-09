import { z } from 'zod';
import { level } from '../primitives';

/** Mirrors `StockTakePostRequest`; counted_qty is decimal(15,4), and zero is real. */
export const stockTakePostSchema = z.object({
    items: z.array(
        z.object({
            id: z.union([z.number(), z.string()]),
            counted_qty: level('counted quantity'),
        }),
    ),
});
