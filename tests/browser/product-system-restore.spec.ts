import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();

type Fixture = {
    email: string;
    password: string;
    import_job_id: number;
    total_rows: number;
    failed_rows: number;
    mode: string;
    contract: string;
};

const fixtureCommand = (mode: 'setup' | 'cleanup'): any => JSON.parse(execFileSync(
    'php',
    ['tests/browser/product-system-restore-fixture.php', mode],
    { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Product SYSTEM RESTORE preview', () => {
    let fixture: Fixture;
    const runtimeErrors: string[] = [];

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('shows a verified SYSTEM RESTORE preview without catalog provenance or schema errors', async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
        page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });

        expect(fixture.mode).toBe('system_restore');
        expect(fixture.contract).toBe('SYSTEM_PRODUCT_RESTORE');
        expect(fixture.failed_rows).toBe(0);

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);

        await page.goto(`/admin/import-preview-page?job=${fixture.import_job_id}`);
        await expect(page.getByText('SYSTEM RESTORE', { exact: true })).toBeVisible();
        await expect(page.getByText('Khôi phục Product ID và các trường đã xuất; không áp dụng quy tắc catalog provenance.')).toBeVisible();
        await expect(page.getByText('Không có lỗi dữ liệu')).toBeVisible();
        await expect(page.locator('body')).not.toContainText('Technical catalog fields require complete appendix provenance');
        await expect(page.locator('body')).not.toContainText('outside the category schema');
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
