import { test as setup } from '@playwright/test';
import { login } from './support';

// Sign in exactly once for the whole suite and persist the session, so every spec
// reuses it instead of re-POSTing /login (which trips the throttle:6,1 rate limiter
// around the 7th spec, and is slower). See playwright.config.ts `dependencies`.
const authFile = 'e2e/.auth/user.json';

setup('authenticate', async ({ page }) => {
    await login(page);
    await page.context().storageState({ path: authFile });
});
