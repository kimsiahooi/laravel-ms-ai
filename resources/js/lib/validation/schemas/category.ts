import { z } from 'zod';
import { optionalText, text } from '../primitives';

/**
 * Mirrors `app/Http/Requests/Tenant/CategoryRequest.php` and the `categories`
 * columns (`name` varchar(255), `description` text).
 *
 * Uniqueness of the name is left to the server — only the database can answer it.
 */
export const categorySchema = z.object({
    name: text(255, 'name'),
    description: optionalText(1000, 'description'),
});
