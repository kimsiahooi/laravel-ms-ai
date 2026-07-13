import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('stock transfers: move stock between two warehouses', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/stock-transfers');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new transfer/i }).click();
    const dialog = page.getByRole('dialog');
    // Source = main warehouse (holds stock for every item), destination = Penang.
    await dialog.getByRole('combobox', { name: 'From warehouse' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await dialog.getByRole('combobox', { name: 'To warehouse' }).click();
    await page.getByRole('option', { name: /penang/i }).click();
    await dialog.getByRole('combobox', { name: 'Item' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.locator('#quantity').fill('1');
    await dialog.getByRole('button', { name: /create transfer/i }).click();

    await expect(page.getByText('Transfer recorded.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
