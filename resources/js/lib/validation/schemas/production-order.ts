import { z } from 'zod';
import { id, optionalIsoDate, optionalText, quantity } from '../primitives';

/**
 * Mirrors `ProductionOrderRequest` and `production_orders.quantity` decimal(15,4).
 *
 * The server additionally refuses a quantity whose exploded material requirement
 * would overflow its own column; that needs the bill of materials, so it stays
 * server-side.
 */
export const productionOrderSchema = z.object({
    product_id: id('product'),
    quantity: quantity(),
    expected_date: optionalIsoDate('expected date'),
    notes: optionalText(1000, 'notes'),
});
