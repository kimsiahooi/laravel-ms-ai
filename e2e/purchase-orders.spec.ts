import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

// The representative transactional write flow: a combobox picker + a line-item
// sub-form + a state transition — the shape every order/return screen shares.
test('purchase orders: create with a line item, then receive it', async ({
    page,
}) => {
    const errors = collectErrors(page);
    await login(page);

    await page.goto('/e2e/purchase-orders');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    // Open the create dialog.
    await page.getByRole('button', { name: /new purchase order/i }).click();
    const dialog = page.getByRole('dialog');

    // Supplier picker — open it and choose the first real supplier ("None" is index 0).
    await dialog.getByRole('combobox', { name: 'Supplier' }).click();
    await page.getByRole('option').nth(1).click();

    // The first line's raw-material picker + quantity + unit cost.
    await dialog.getByRole('combobox', { name: 'Raw material' }).click();
    await page.getByRole('option').nth(1).click();
    await dialog.getByLabel('Quantity').fill('10');
    await dialog.getByLabel('Unit cost').fill('2.5');

    await dialog
        .getByRole('button', { name: /create purchase order/i })
        .click();
    await expect(page.getByText('Purchase order created.')).toBeVisible();

    // The new PO is newest-first, so the first row is ours — receive it into a warehouse.
    await page
        .getByRole('button', { name: /actions for order/i })
        .first()
        .click();
    await page.getByRole('menuitem', { name: /receive/i }).click();

    const receiveDialog = page.getByRole('dialog');
    await receiveDialog.getByRole('combobox', { name: 'Receive into' }).click();
    await page.getByRole('option').nth(1).click();
    await receiveDialog
        .getByRole('button', { name: 'Receive', exact: true })
        .click();

    await expect(page.getByText('Purchase order received.')).toBeVisible();

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
