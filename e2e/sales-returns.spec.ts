import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

test('sales returns: create with a line item, then complete it', async ({
    page,
}) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/sales-returns');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /new return/i }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('combobox', { name: 'Customer' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByRole('combobox', { name: 'Product' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByLabel('Quantity').fill('2');
    await dialog.getByRole('button', { name: /create sales return/i }).click();
    await expect(page.getByText('Sales return created.')).toBeVisible();

    // Completing a sales return adds stock back — any warehouse works.
    await page
        .getByRole('button', { name: /actions for return/i })
        .first()
        .click();
    await page.getByRole('menuitem', { name: /complete/i }).click();
    const completeDialog = page.getByRole('dialog');
    await completeDialog.getByRole('combobox', { name: 'Return into' }).click();
    await page.getByRole('option', { name: /kuala lumpur/i }).click();
    await completeDialog
        .getByRole('button', { name: 'Complete return' })
        .click();
    await expect(page.getByText('Sales return completed.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
