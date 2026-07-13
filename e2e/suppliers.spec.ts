import { expect, test } from '@playwright/test';
import { collectErrors, login } from './support';

// The canonical catalog CRUD flow in a real browser: it exercises the shared
// DataTable + ResourceFormDialog + RowActions + ConfirmDeleteDialog + ExportMenu
// that categories / customers / locations / raw-materials all reuse.
test('suppliers: create, search, export, edit and delete', async ({ page }) => {
    const errors = collectErrors(page);
    await login(page);

    const name = `PW Supplier ${Date.now()}`;
    const renamed = `${name} edited`;

    await page.goto('/e2e/suppliers');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    // Create
    await page.getByRole('button', { name: /new supplier/i }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill(name);
    await dialog.getByLabel(/email/i).fill('pw@example.test');
    await dialog.getByRole('button', { name: /create supplier/i }).click();
    await expect(page.getByRole('cell', { name, exact: true })).toBeVisible();

    // Search narrows the list to the new row.
    await page.getByRole('textbox', { name: 'Search' }).fill(name);
    await expect(page.getByRole('cell', { name, exact: true })).toBeVisible();

    // Export — clicking CSV starts a file download (of the filtered set).
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export' }).click();
    await page.getByRole('menuitem', { name: /csv/i }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('suppliers');

    // Edit
    await page.getByRole('button', { name: `Actions for ${name}` }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    await dialog.getByLabel('Name').fill(renamed);
    await dialog.getByRole('button', { name: /save changes/i }).click();
    await expect(
        page.getByRole('cell', { name: renamed, exact: true }),
    ).toBeVisible();

    // Delete
    await page.getByRole('button', { name: `Actions for ${renamed}` }).click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete' })
        .click();
    await expect(
        page.getByRole('cell', { name: renamed, exact: true }),
    ).toHaveCount(0);

    expect(errors, `console/page errors:\n${errors.join('\n')}`).toEqual([]);
});
