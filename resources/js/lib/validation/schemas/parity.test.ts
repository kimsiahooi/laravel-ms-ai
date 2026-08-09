import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { bomSchema } from './bom';
import { categorySchema } from './category';
import { customerSchema } from './customer';
import { locationSchema } from './location';
import { productSchema } from './product';
import { productionOrderSchema } from './production-order';
import { purchaseOrderSchema } from './purchase-order';
import { purchaseReturnSchema } from './purchase-return';
import { rawMaterialSchema } from './raw-material';
import { roleSchema } from './role';
import { salesOrderSchema } from './sales-order';
import { salesReturnSchema } from './sales-return';
import { stockMovementSchema } from './stock-movement';
import { stockTakeSchema } from './stock-take';
import { stockTransferSchema } from './stock-transfer';
import { supplierSchema } from './supplier';
import { userSchema } from './user';
import { warehouseSchema } from './warehouse';
import { warehouseReorderLevelSchema } from './warehouse-reorder-level';

/**
 * These schemas exist to refuse, in the browser, exactly what the server refuses.
 * That only holds while the two lists of fields agree — so this reads the real
 * FormRequests and checks that every field the server validates is one the
 * browser knows about too.
 *
 * Deliberately field-level, not rule-level: pinning every rule string would break
 * on harmless rewording, while a *missing field* is the failure that actually
 * matters — it means a value reaches the server unchecked.
 */

const REQUESTS = join(process.cwd(), 'app/Http/Requests/Tenant');

/** The top-level field names a FormRequest's `rules()` returns. */
function laravelFields(request: string): string[] {
    const php = readFileSync(join(REQUESTS, `${request}.php`), 'utf8');
    const body = php.slice(php.indexOf('public function rules'));
    const keys = [...body.matchAll(/^\s{12}'([^']+)'\s*=>/gm)].map((m) => m[1]);

    // `items.*.quantity` is part of `items`, which is already its own key.
    return [...new Set(keys.filter((key) => !key.includes('.')))];
}

/** The keys a zod object schema knows, unwrapping any effects wrapped around it. */
function zodFields(schema: z.ZodTypeAny): string[] {
    let current: z.ZodTypeAny = schema;

    // `.refine()` / `.superRefine()` wrap the object; the shape is underneath.
    while (!(current instanceof z.ZodObject) && 'def' in current) {
        const inner = (current.def as { innerType?: z.ZodTypeAny }).innerType;
        if (!inner) break;
        current = inner;
    }

    return current instanceof z.ZodObject ? Object.keys(current.shape) : [];
}

/** Requests with no hand-written schema, and why that is correct. */
const EXEMPT = [
    // Declares no rules of its own — it is the shared base class.
    'TenantFormRequest',
    // Validated from the payload each builds, not from a page's fields:
    // the stock-take counts and the generated settings form.
    'StockTakePostRequest',
    'SettingsUpdateRequest',
    // Read-only lookups behind the item picker and the barcode scanner. They
    // take query parameters, not a form anyone fills in.
    'StockOnHandRequest',
    'StockResolveItemRequest',
];

const pairs: [string, z.ZodTypeAny][] = [
    ['CategoryRequest', categorySchema],
    ['LocationRequest', locationSchema],
    ['WarehouseRequest', warehouseSchema],
    ['SupplierRequest', supplierSchema],
    ['CustomerRequest', customerSchema(['MY', 'SG'])],
    ['RawMaterialRequest', rawMaterialSchema],
    ['ProductRequest', productSchema],
    ['UserRequest', userSchema(false)],
    ['RoleRequest', roleSchema],
    ['StockTakeRequest', stockTakeSchema],
    ['StockMovementRequest', stockMovementSchema],
    ['StockTransferRequest', stockTransferSchema],
    ['ProductionOrderRequest', productionOrderSchema],
    ['PurchaseOrderRequest', purchaseOrderSchema(['MYR'])],
    ['SalesOrderRequest', salesOrderSchema(['MYR'])],
    ['PurchaseReturnRequest', purchaseReturnSchema],
    ['SalesReturnRequest', salesReturnSchema],
    ['BomRequest', bomSchema],
    ['WarehouseReorderLevelRequest', warehouseReorderLevelSchema],
];

describe('every server-validated field is checked in the browser too', () => {
    it.each(pairs)('%s', (request, schema) => {
        const missing = laravelFields(request).filter(
            (field) => !zodFields(schema).includes(field),
        );

        expect(missing).toEqual([]);
    });

    it('covers every request that has a matching schema', () => {
        // If a new FormRequest lands without a schema, this is what says so.
        const all = readdirSync(REQUESTS)
            .filter((file) => file.endsWith('Request.php'))
            .map((file) => file.replace('.php', ''))
            .filter((name) => !EXEMPT.includes(name));

        const covered = pairs.map(([request]) => request);

        expect(all.filter((name) => !covered.includes(name))).toEqual([]);
    });
});
