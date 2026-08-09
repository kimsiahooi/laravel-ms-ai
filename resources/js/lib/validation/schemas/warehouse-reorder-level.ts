import { z } from 'zod';
import { id, level, oneOf } from '../primitives';

/** Mirrors `WarehouseReorderLevelRequest`; min_stock is decimal(15,4). */
export const warehouseReorderLevelSchema = z.object({
    stockable_type: oneOf(['product', 'raw_material'], 'item type'),
    stockable_id: id('item'),
    min_stock: level('reorder level'),
});
