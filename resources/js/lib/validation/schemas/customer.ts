import { z } from 'zod';
import { oneOf, optionalEmail, optionalText, text } from '../primitives';

/**
 * Mirrors `CustomerRequest` and the `customers` columns. The e-invoice identity
 * fields are capped by their columns (tin / registration_no /
 * sst_registration_no / city 100, postcode 20, state_code 10, country_code 2).
 */
export const customerSchema = (countries: readonly string[]) =>
    z.object({
        name: text(255, 'name'),
        contact_person: optionalText(255, 'contact person'),
        email: optionalEmail(255),
        tin: optionalText(100, 'TIN'),
        registration_no: optionalText(100, 'registration number'),
        sst_registration_no: optionalText(100, 'SST/GST registration number'),
        phone: optionalText(50, 'phone'),
        address: optionalText(1000, 'address'),
        city: optionalText(100, 'city'),
        postcode: optionalText(20, 'postcode'),
        state_code: optionalText(10, 'state code'),
        // Not merely "two characters" — a made-up code travels into an e-invoice.
        country_code: z
            .union([z.literal(''), oneOf(countries, 'country')])
            .optional(),
        notes: optionalText(1000, 'notes'),
    });
