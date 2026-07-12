import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('dashboard: load + apply a date-range filter without errors (regresses B1/B2)', async ({
    page,
}) => {
    const errors = collectErrors(page);

    await login(page);

    // Initial render: heading + the analytics chart card.
    await expect(
        page.getByRole('heading', { name: 'Dashboard', level: 1 }),
    ).toBeVisible();
    await expect(page.getByText('Sales vs Purchases')).toBeVisible();

    // Apply a date-range preset — this is the exact action that returned a 500 before
    // the CarbonImmutable fix (B1). The range trigger's label carries an en-dash.
    await page.locator('button', { hasText: '–' }).first().click();
    await page.getByRole('button', { name: 'Last 30 days' }).click();
    await page.getByRole('button', { name: 'Apply range' }).click();

    // The filtered reload lands (URL carries the range) and the page still renders.
    await expect.poll(() => page.url()).toContain('from=');
    await expect(
        page.getByRole('heading', { name: 'Dashboard', level: 1 }),
    ).toBeVisible();
    await expect(page.getByText('Sales vs Purchases')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
