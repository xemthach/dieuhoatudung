import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/product-technical-edit-fixture.php', mode], { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Product technical edit and BTU hydration', () => {
    let fixture: any;
    const runtimeErrors: string[] = [];

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('hydrates technical BTU, saves an audited override, and preserves source evidence', async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
        page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);

        await page.goto(`/admin/products/${fixture.product_id}/edit`);
        await page.getByText('Thông số kỹ thuật', { exact: true }).click();

        const btu = page.locator('#form\\.technical_capacity_btu');
        const kw = page.locator('#form\\.capacity_kw');
        const hp = page.locator('#form\\.hp');
        await expect(btu).toBeEditable();
        await expect(kw).toBeEditable();
        await expect(hp).toBeEditable();
        await expect(btu).toHaveValue('12300');
        await expect(kw).toHaveValue('3.6');
        await expect(hp).toHaveValue('1.5');

        await btu.fill('12400');
        await kw.fill('3.7');
        await hp.fill('1.6');
        await page.locator('#form\\.voltage').fill('220V');
        await page.locator('#form\\.technical_specs_override_reason').fill('Browser-certified installation correction');
        await page.getByRole('button', { name: /Lưu thay đổi/i }).click();
        await page.waitForTimeout(1_500);

        const snapshot = fixtureCommand('snapshot');
        expect(snapshot.technical_capacity_btu).toBe(12400);
        expect(snapshot.capacity_kw).toBe('3.70');
        expect(snapshot.hp).toBe('1.6');
        expect(snapshot.voltage).toBe('220V');
        expect(snapshot.source).toBe('manual_override');
        expect(snapshot.reason).toBe('Browser-certified installation correction');
        expect(snapshot.overridden_at).toBeTruthy();
        expect(snapshot.spec_capacity_kw).toBe('3.6');

        await page.reload();
        await page.getByText('Thông số kỹ thuật', { exact: true }).click();
        await expect(btu).toHaveValue('12400');
        await expect(kw).toHaveValue('3.7');
        await expect(hp).toHaveValue('1.6');
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
