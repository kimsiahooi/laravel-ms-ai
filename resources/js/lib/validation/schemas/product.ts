import { z } from 'zod';
import { boolFlag, image, optionalId, optionalText, text } from '../primitives';

/**
 * Mirrors `ProductRequest` and the `products` columns. The sku/barcode/unit
 * columns were varchar(255) by omission and have been narrowed to the 100/100/20
 * these rules always claimed.
 */
export const productSchema = z.object({
    name: text(255, 'name'),
    sku: text(100, 'SKU'),
    barcode: optionalText(100, 'barcode'),
    description: optionalText(2000, 'description'),
    category_id: optionalId('category'),
    supplier_id: optionalId('supplier'),
    unit: text(20, 'unit'),
    image: image(2048),
    remove_image: boolFlag(),
});
