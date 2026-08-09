import { z } from 'zod';
import { email, text } from '../primitives';

/**
 * Mirrors `UserRequest`. A password is required when creating and optional when
 * editing, where blank means "leave it alone". How strong it must be is the
 * server's call — `Password::default()` is the authority on that.
 */
export const userSchema = (isEdit: boolean) =>
    z.object({
        name: text(255, 'name'),
        email: email(255),
        role: text(100, 'role'),
        password: isEdit
            ? z.string().optional()
            : z.string().min(1, 'The password field is required.'),
    });
