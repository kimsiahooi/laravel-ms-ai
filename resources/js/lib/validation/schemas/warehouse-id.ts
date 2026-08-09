import { z } from 'zod';
import { id } from '../primitives';

/** The single-field form behind Receive / Fulfil / Complete. */
export const warehouseIdSchema = z.object({ warehouse_id: id('warehouse') });
