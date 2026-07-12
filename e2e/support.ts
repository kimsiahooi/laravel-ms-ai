import type { Page } from '@playwright/test';

/** Signs in as the seeded e2e admin and waits for the dashboard. */
export async function login(page: Page): Promise<void> {
    await page.goto('/e2e/login');
    await page.fill('input[name="email"]', 'e2e@example.test');
    await page.fill('input[name="password"]', 'password');
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
