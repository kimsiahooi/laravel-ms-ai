import { z } from 'zod';
import {
    exchangeRate,
    id,
    lines,
    money,
    oneOf,
    optionalIsoDate,
    optionalText,
    quantity,
} from '../primitives';

/**
 * Mirrors `PurchaseOrderRequest`. Quantity and unit_cost are decimal(15,4);
 * exchange_rate is decimal(15,6); number is varchar(50).
 */
export const purchaseOrderSchema = (currencies: readonly string[]) =>
    z.object({
        supplier_id: id('supplier'),
        currency: oneOf(currencies, 'currency'),
        exchange_rate: exchangeRate(),
        number: optionalText(50, 'order number'),
        expected_date: optionalIsoDate('expected date'),
        notes: optionalText(1000, 'notes'),
        items: lines(
            z.object({
                raw_material_id: id('raw material'),
                quantity: quantity(),
                unit_cost: money('unit cost'),
            }),
        ),
    });
