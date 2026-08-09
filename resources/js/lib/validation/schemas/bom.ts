import { z } from 'zod';
import { id, lines, quantity } from '../primitives';

/**
 * Mirrors `BomRequest`; `bom_items.quantity` is decimal(15,4). A per-unit amount
 * rounded here is multiplied by every future build, so the scale matters more
 * here than anywhere else. An empty bill is legitimate — that is how one is cleared.
 */
export const bomSchema = z.object({
    items: lines(
        z.object({ raw_material_id: id('raw material'), quantity: quantity() }),
        { min: 0 },
    ),
});
