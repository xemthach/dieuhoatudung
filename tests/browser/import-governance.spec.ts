import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/import-governance-fixture.php', mode], { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Import governance admin', () => {
    let fixture: any;
    const runtimeErrors: string[] = [];

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('changes an auditable business policy and restores it while integrity rules stay locked', async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
        page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);
        await page.goto('/admin/import-export-governance');

        await expect(page.getByText('product_transfer.detach_catalog_lineage', { exact: true })).toBeVisible();
        await expect(page.getByText(/LOCKED/).first()).toBeVisible();
        await expect(page.locator('[data-policy-save="integrity.manifest"]')).toHaveCount(0);

        const mode = page.locator('[data-policy-mode="product_transfer.detach_catalog_lineage"]');
        const reason = page.locator('[data-policy-reason="product_transfer.detach_catalog_lineage"]');
        page.once('dialog', dialog => dialog.accept());
        await mode.selectOption(fixture.changed_mode);
        await reason.fill('Playwright governed policy certification');
        await page.locator('[data-policy-save="product_transfer.detach_catalog_lineage"]').click();
        await expect.poll(() => fixtureCommand('snapshot').mode).toBe(fixture.changed_mode);

        await page.reload();
        await expect(mode).toHaveValue(fixture.changed_mode);
        page.once('dialog', dialog => dialog.accept());
        await mode.selectOption(fixture.original_mode);
        await reason.fill('Restore pre-test governance state');
        await page.locator('[data-policy-save="product_transfer.detach_catalog_lineage"]').click();
        await expect.poll(() => fixtureCommand('snapshot').mode).toBe(fixture.original_mode);
        expect(fixtureCommand('snapshot').audit_count).toBe(2);
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
