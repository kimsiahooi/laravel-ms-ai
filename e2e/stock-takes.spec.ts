import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('stock takes: start a count for a warehouse', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/stock-takes');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new stock take/i }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('combobox', { name: 'Warehouse' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await dialog.getByRole('button', { name: /create stock take/i }).click();

    // Starting a count snapshots on-hand and drops you on its detail page.
    await expect(page.getByText('Stock take started.')).toBeVisible();
    await expect.poll(() => page.url()).toMatch(/\/e2e\/stock-takes\/\d+/);

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
