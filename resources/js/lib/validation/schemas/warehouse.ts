import { z } from 'zod';
import { id, optionalText, text } from '../primitives';

/** Mirrors `WarehouseRequest` and the `warehouses` columns (code varchar(50)). */
export const warehouseSchema = z.object({
    location_id: id('location'),
    name: text(255, 'name'),
    code: optionalText(50, 'code'),
    address: optionalText(1000, 'address'),
});
