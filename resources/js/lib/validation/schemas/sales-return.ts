import { z } from 'zod';
import { id, lines, optionalText, quantity } from '../primitives';

/** Mirrors `SalesReturnRequest`; quantity is decimal(15,4). */
export const salesReturnSchema = z.object({
    customer_id: id('customer'),
    notes: optionalText(1000, 'notes'),
    items: lines(z.object({ product_id: id('product'), quantity: quantity() })),
});
