import type { Page } from '@playwright/test';

/**
 * Signs in as the seeded e2e admin and waits for the dashboard. When the browser
 * context is already authenticated (specs reuse a saved storageState — see
 * auth.setup.ts), visiting /login redirects straight to the dashboard, so this is a
 * cheap no-op that keeps the one real sign-in off the login rate limiter.
 */
export async function login(page: Page): Promise<void> {
    await page.goto('/e2e/login');
    if (page.url().includes('/dashboard')) {
        return;
    }

    const email = page.locator('input[name="email"]');
    const password = page.locator('input[name="password"]');
    await email.waitFor({ state: 'visible' });

    // SSR is off for e2e, so the inputs are controlled by a client-rendered form. A
    // fill that lands before hydration is dropped and React resets the field to empty,
    // submitting a blank form that bounces back to /login. Fill, then confirm the value
    // actually stuck (re-fill if hydration ate it) before submitting.
    for (let attempt = 0; attempt < 4; attempt++) {
        await email.fill('e2e@example.test');
        await password.fill('password');
        if ((await email.inputValue()) === 'e2e@example.test') {
            break;
        }
        await page.waitForTimeout(150);
    }

    await page.getByRole('button', { name: /sign in/i }).click();
    await page.waitForURL('**/e2e/dashboard');
}

/** Collects console + uncaught page errors into an array for post-flow assertions. */
export function collectErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error')
            errors.push(`${page.url()} — ${msg.text()}`);
    });
    page.on('pageerror', (err) =>
        errors.push(`${page.url()} — ${err.message}`),
    );
    return errors;
}
