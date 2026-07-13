import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('stock movements: record an incoming movement', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/stock-movements');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new movement/i }).click();
    const dialog = page.getByRole('dialog');
    // The form defaults to "In" (adds stock), so item + warehouse + qty is enough.
    await dialog.getByRole('combobox', { name: 'Item' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByRole('combobox', { name: 'Warehouse' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await dialog.locator('#quantity').fill('5');
    await dialog.getByRole('button', { name: /create movement/i }).click();

    await expect(page.getByText('Movement recorded.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
