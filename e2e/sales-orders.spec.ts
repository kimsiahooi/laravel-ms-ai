import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('sales orders: create with a line item, then fulfill it', async ({
    page,
}) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/sales-orders');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new sales order/i }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('combobox', { name: 'Customer' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByRole('combobox', { name: 'Product' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByLabel('Quantity').fill('2');
    await dialog.getByLabel('Unit price').fill('10');
    await dialog.getByRole('button', { name: /create sales order/i }).click();
    await expect(page.getByText('Sales order created.')).toBeVisible();

    // Fulfill the new (newest-first) order from the main warehouse, which holds stock.
    await page
        .getByRole('button', { name: /actions for order/i })
        .first()
        .click();
    await page.getByRole('menuitem', { name: /fulfill/i }).click();
    const fulfillDialog = page.getByRole('dialog');
    await fulfillDialog.getByRole('combobox', { name: 'Fulfill from' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await fulfillDialog
        .getByRole('button', { name: 'Fulfill', exact: true })
        .click();
    await expect(page.getByText('Sales order fulfilled.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
