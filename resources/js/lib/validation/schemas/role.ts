import { z } from 'zod';
import { text } from '../primitives';

/**
 * Mirrors `RoleRequest`; `roles.name` is varchar(100). Which permissions exist is
 * the server's list to police.
 */
export const roleSchema = z.object({
    name: text(100, 'name'),
    permissions: z.array(z.string()).optional(),
});
