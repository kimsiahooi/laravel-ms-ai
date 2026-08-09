import { z } from 'zod';
import { optionalText, text } from '../primitives';

/** Mirrors `LocationRequest` and the `locations` columns (code varchar(50)). */
export const locationSchema = z.object({
    name: text(255, 'name'),
    code: optionalText(50, 'code'),
    address: optionalText(1000, 'address'),
});
