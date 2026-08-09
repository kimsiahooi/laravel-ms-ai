import { z } from 'zod';
import { email, text } from '../primitives';

/**
 * Mirrors `app/Http/Requests/Central/StoreTenantRequest.php` and the central
 * `tenants` table (`id` is the slug, varchar(50)).
 *
 * The slug becomes the workspace's URL and its database name, so its shape is
 * checked here as well as on the server; whether it is already taken, or one of
 * the reserved words, only the server can say.
 */
export const createTenantSchema = z.object({
    name: text(255, 'name'),
    slug: text(50, 'slug').regex(
        /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
        'The slug may only use lowercase letters, numbers and single hyphens.',
    ),
    admin_name: text(255, 'admin name'),
    admin_email: email(255, 'admin email'),
    admin_password: z
        .string()
        .min(8, 'The admin password must be at least 8 characters.'),
    seed_demo_data: z.enum(['0', '1']).optional(),
});
