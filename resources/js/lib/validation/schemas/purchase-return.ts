import { z } from 'zod';
import { id, lines, optionalText, quantity } from '../primitives';

/** Mirrors `PurchaseReturnRequest`; quantity is decimal(15,4). */
export const purchaseReturnSchema = z.object({
    supplier_id: id('supplier'),
    notes: optionalText(1000, 'notes'),
    items: lines(
        z.object({ raw_material_id: id('raw material'), quantity: quantity() }),
    ),
});
