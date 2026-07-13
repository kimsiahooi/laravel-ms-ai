import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('production orders: create from a BOM, then complete it', async ({
    page,
}) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/production-orders');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new production order/i }).click();
    const dialog = page.getByRole('dialog');
    // Only products with a BOM are listed; pick the first and build 2.
    await dialog.getByRole('combobox', { name: 'Product' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByLabel('Quantity').fill('2');
    await dialog
        .getByRole('button', { name: /create production order/i })
        .click();
    await expect(page.getByText('Production order created.')).toBeVisible();

    // Complete it from the main warehouse, which holds the BOM's raw materials.
    await page
        .getByRole('button', { name: /actions for order/i })
        .first()
        .click();
    await page.getByRole('menuitem', { name: /complete/i }).click();
    const completeDialog = page.getByRole('dialog');
    await completeDialog.getByRole('combobox', { name: 'Warehouse' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await completeDialog
        .getByRole('button', { name: 'Complete build' })
        .click();
    await expect(page.getByText('Production order completed.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
