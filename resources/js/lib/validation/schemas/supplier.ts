import { z } from 'zod';
import { optionalEmail, optionalText, text } from '../primitives';

/** Mirrors `SupplierRequest` and the `suppliers` columns (tax_id 100, phone 50). */
export const supplierSchema = z.object({
    name: text(255, 'name'),
    contact_person: optionalText(255, 'contact person'),
    email: optionalEmail(255),
    tax_id: optionalText(100, 'tax ID'),
    phone: optionalText(50, 'phone'),
    address: optionalText(1000, 'address'),
    notes: optionalText(1000, 'notes'),
});
