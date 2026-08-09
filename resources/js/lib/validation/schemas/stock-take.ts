import { z } from 'zod';
import { id, optionalText } from '../primitives';

/** Mirrors `StockTakeRequest` — starting a count needs somewhere to count. */
export const stockTakeSchema = z.object({
    warehouse_id: id('warehouse'),
    notes: optionalText(1000, 'notes'),
});
