import { z } from 'zod';
import { email, text } from '../primitives';

/** Both sign-in forms. How strong a password must be is the server's business. */
export const loginSchema = z.object({
    email: email(255),
    password: z.string().min(1, 'The password field is required.'),
});

/** The profile form on the settings page. */
export const profileSchema = z.object({
    name: text(255, 'name'),
    email: email(255),
});

/** Deleting your own account asks for your password as confirmation. */
export const deleteAccountSchema = z.object({
    password: z.string().min(1, 'The password field is required.'),
});
