import { execFileSync } from 'node:child_process';
import { expect, test, Page } from '@playwright/test';

const fixture = (mode: string): any => JSON.parse(execFileSync('php', ['tests/browser/comprehensive-fixture.php', mode], { encoding: 'utf8' }));
let state: any;
test.beforeAll(() => { state = fixture('setup'); });
test.afterAll(() => { fixture('cleanup'); });
async function login(page: Page, actor = state) {
    await page.goto('/admin/login');
    await page.locator('[id="form.email"]').fill(actor.email);
    await page.locator('[id="form.password"]').fill(actor.password);
    await page.locator('button[type=submit]').click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}
async function saveSettings(page: Page) {
    const pending = page.waitForRequest(request => request.method() === 'POST' && (request.postData() ?? '').includes('saveSettings'));
    await page.locator('button[wire\\:click="saveSettings"]').last().click({ noWaitAfter: true });
    const response = await (await pending).response();
    expect(response?.status()).toBe(200);
    if (response) {
        const body = await response.json();
        for (const component of body.components ?? []) {
            const snapshot = JSON.parse(component.snapshot);
            expect(snapshot.memo.errors, 'Settings validation errors').toEqual([]);
        }
    }
}
test('review stars submit asynchronously and contact details stay private', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(state.dealer_url);
    const original = page.url();
    const form = page.locator('[data-dealer-review-form]');
    await form.locator('[name=customer_name]').fill('Private Review Guest');
    await form.getByRole('radio', { name: '5 sao', exact: true }).check();
    await form.locator('[name=content]').fill('Dịch vụ lắp đặt rất chu đáo và chuyên nghiệp.');
    await form.locator('[name=customer_email]').fill('private-review@example.test');
    await form.getByRole('button', { name: 'Gửi đánh giá' }).click();
    await expect(page.locator('[data-review-success]')).toBeVisible();
    await expect(form).toBeHidden();
    await expect(page).toHaveURL(original);
    expect(fixture('snapshot').reviews).toBe(1);
    await page.reload();
    await expect(page.locator('body')).not.toContainText('private-review@example.test');
    expect(errors).toEqual([]);
});
test('product and dealer pages fit all supported viewports', async ({ page }) => {
    test.setTimeout(180_000);
    for (const width of [320, 360, 390, 440, 768, 1024]) {
        await page.setViewportSize({ width, height: 844 });
        for (const path of ['/san-pham?q=COMPREHENSIVE-001', '/dai-ly', state.dealer_url]) {
            await page.goto(path);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width} ${path}`).toBe(true);
        }
        await page.goto('/san-pham?q=COMPREHENSIVE-001');
        const card = page.locator(`#product-card-${state.product_id}`);
        await expect(card).toBeVisible();
        await expect(card).toContainText('123,456,789');
        const bounds = await card.boundingBox();
        expect(bounds!.width).toBeLessThanOrEqual(width);
    }
});
test('homepage toggles and order survive save and reload', async ({ page }) => {
    test.setTimeout(120_000);
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await login(page);
    await page.goto('/admin/manage-settings');
    await page.getByRole('tab', { name: 'Display', exact: true }).click();
    const toggle = page.locator('[id*="homepage_section_order"][id$=".enabled"]').first();
    await expect(toggle).toBeVisible();
    await toggle.click();
    await saveSettings(page);
    await expect.poll(() => fixture('snapshot').layout?.[0]?.enabled).toBe(false);
    await page.reload();
    await page.getByRole('tab', { name: 'Display', exact: true }).click();
    await expect(toggle).not.toBeChecked();
    await toggle.click();
    await saveSettings(page);
    await expect.poll(() => fixture('snapshot').layout?.[0]?.enabled).toBe(true);
    expect(errors).toEqual([]);
});
test('super admin approves and applies the reviewed draft in one dialog', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page, { email: state.operator_email, password: state.operator_password });
    await page.goto(`/admin/products/${state.product_id}/edit`);
    await page.getByRole('button', { name: 'Duyệt và áp dụng', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Duyệt và áp dụng nội dung AI', exact: true });
    await expect(dialog.getByRole('button', { name: 'Xác nhận', exact: true })).toBeVisible();
    await dialog.getByRole('textbox', { name: /Mã xác nhận áp dụng/ }).fill(state.confirmation);
    await dialog.getByRole('button', { name: /Xác nhận|Confirm/ }).last().click();
    await expect.poll(() => fixture('snapshot').draft.approval_status).toBe('APPLIED');
});
test('business file imports through upload, preview and execution', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    const response = await page.goto('/admin/data-transfer-page');
    expect(response?.status()).toBe(200);
    await page.getByRole('button', { name: 'Import Dữ liệu', exact: true }).click();
    const dialog = page.locator('.fi-modal-window').last();
    await dialog.locator('select').first().selectOption('product');
    await page.waitForLoadState('networkidle');
    await dialog.locator('select').filter({ has: page.locator('option[value="csv"]') }).selectOption('csv');
    await dialog.locator('select').filter({ has: page.locator('option[value="business"]') }).selectOption('business');
    await dialog.locator('select').filter({ has: page.locator('option[value="upsert"]') }).selectOption('update');
    await page.waitForLoadState('networkidle');
    await dialog.locator('input[type=file]').setInputFiles({ name:'business.csv', mimeType:'text/csv', buffer:Buffer.from('sku,regular_price\nCOMPREHENSIVE-001,234567890\n') });
    await expect(dialog.locator('.filepond--item')).toBeVisible();
    await dialog.getByRole('button', { name: 'Xem trước dữ liệu', exact: true }).click();
    await expect(page).toHaveURL(/import-preview/);
    await page.getByRole('button', { name: 'Xác nhận Import', exact: true }).click();
    await page.getByRole('button', { name: 'Xác nhận và Import', exact: true }).click();
    await expect.poll(() => Number(fixture('snapshot').price)).toBe(234567890);
});
