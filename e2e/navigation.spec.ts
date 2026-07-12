import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

// Key tenant pages — visiting each in a real browser catches render/500/JS errors that
// the jsdom (Vitest) layer can't (real SSR-off client render + real routing).
const PAGES = [
    '/e2e/dashboard',
    '/e2e/reports',
    '/e2e/warehouses',
    '/e2e/products',
    '/e2e/stock-takes',
    '/e2e/activity',
];

test('key tenant pages render in a real browser without errors', async ({
    page,
}) => {
    const errors = collectErrors(page);

    await login(page);

    for (const path of PAGES) {
        await page.goto(path);
        // Every screen has exactly one h1 — its presence proves the page mounted.
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    }

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
