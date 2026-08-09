import { z } from 'zod';
import { optionalText, text } from '../primitives';

/** Mirrors `RawMaterialRequest` and `raw_materials` (sku/barcode 100, unit 20). */
export const rawMaterialSchema = z.object({
    name: text(255, 'name'),
    sku: text(100, 'SKU'),
    barcode: optionalText(100, 'barcode'),
    unit: text(20, 'unit'),
});
