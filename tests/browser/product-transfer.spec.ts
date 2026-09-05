import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/product-transfer-fixture.php', mode], { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Product Transfer browser contract', () => {
    let fixture: any;
    const runtimeErrors: string[] = [];
    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('previews slug remaps and confirms a signed transfer without catalog provenance errors', async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
        page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });
        expect(fixture.contract).toBe('PRODUCT_TRANSFER');
        expect(fixture.valid).toBe(1);
        expect(fixture.errors).toBe(0);

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);
        await page.goto(`/admin/import-preview-page?job=${fixture.import_job_id}`);
        await expect(page.getByText('PRODUCT TRANSFER', { exact: true })).toBeVisible();
        await expect(page.getByText(/REMAPPED 1/).first()).toBeVisible();
        await expect(page.locator('body')).not.toContainText('Technical catalog fields require complete appendix provenance');

        await page.getByRole('button', { name: /Import/i }).first().click();
        const confirm = page.getByRole('button', { name: /và Import/i });
        await expect(confirm).toBeVisible();
        await confirm.click();
        await expect(page).toHaveURL(/\/admin\/import-result-page/);
        await expect.poll(() => fixtureCommand('snapshot').status).toBe('completed');
        const snapshot = fixtureCommand('snapshot');
        expect(snapshot.created).toBe(1);
        expect(snapshot.brand_id).toBe(fixture.target_brand_id);
        expect(snapshot.category_id).toBe(fixture.target_category_id);
        expect(snapshot.marketing).toBe(18000);
        expect(snapshot.technical).toBe(17100);
        expect(snapshot.kw).toBe('5.00');
        expect(snapshot.provenance).toBe('PRODUCT_TRANSFER');
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
