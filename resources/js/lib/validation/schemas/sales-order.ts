import { z } from 'zod';
import {
    boolFlag,
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
 * Mirrors `SalesOrderRequest`. Quantity and unit_price are decimal(15,4);
 * exchange_rate is decimal(15,6); number is varchar(50).
 */
export const salesOrderSchema = (currencies: readonly string[]) =>
    z.object({
        customer_id: id('customer'),
        currency: oneOf(currencies, 'currency'),
        exchange_rate: exchangeRate(),
        number: optionalText(50, 'order number'),
        expected_date: optionalIsoDate('expected date'),
        notes: optionalText(1000, 'notes'),
        items: lines(
            z.object({
                product_id: id('product'),
                quantity: quantity(),
                unit_price: money('unit price'),
                taxable: boolFlag(),
            }),
        ),
    });
