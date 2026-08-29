import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
let fixture: { email: string; password: string };
const errors: string[] = [];

test.describe.serial('Admin navigation governance round-trip', () => {
    test.beforeAll(() => {
        fixture = JSON.parse(execFileSync('php', ['tests/browser/fixture.php', 'setup'], { cwd: root, encoding: 'utf8' }));
    });

    test.afterAll(() => {
        execFileSync('php', ['tests/browser/fixture.php', 'cleanup'], { cwd: root, encoding: 'utf8' });
    });

    test('edits, targets, reorders, disables and restores a safe menu item', async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') errors.push(`console:${message.text()}`); });
        page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => errors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) errors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);
        await page.goto('/admin/manage-settings');

        const navigationSection = page.locator('section').filter({ hasText: 'Điều hướng public' }).first();
        await expect(navigationSection).toBeVisible();
        await navigationSection.getByText('Điều hướng public', { exact: true }).click();

        const inputs = page.locator('input');
        const labelIndex = await inputs.evaluateAll(elements => elements.findIndex(element => (element as HTMLInputElement).value === 'Sản phẩm kiểm thử'));
        if (labelIndex < 0) {
            throw new Error('Navigation repeater label input was not rendered: ' + JSON.stringify(await inputs.evaluateAll(elements => elements.map(element => ({ name: element.getAttribute('name'), value: (element as HTMLInputElement).value, aria: element.getAttribute('aria-label') })).slice(-20))));
        }
        const firstLabel = inputs.nth(labelIndex);
        const originalLabel = await firstLabel.inputValue();
        await firstLabel.fill('Sản phẩm kiểm thử');

        const typeIndex = await page.locator('select').evaluateAll(elements => elements.findIndex(element => (element as HTMLSelectElement).value === 'route'));
        if (typeIndex < 0) throw new Error('Navigation type select was not rendered');
        const type = page.locator('select').nth(typeIndex);
        await type.selectOption('product_category');
        await page.waitForTimeout(5000);
        const categoryIndex = await page.locator('select').evaluateAll(elements => elements.findIndex(element => (element as HTMLSelectElement).options.length > 1 && Array.from((element as HTMLSelectElement).options).some(option => option.textContent?.includes('Cassette'))));
        if (categoryIndex < 0) throw new Error('Navigation category select was not rendered: ' + JSON.stringify(await page.locator('select').evaluateAll(elements => elements.map(element => ({ value: (element as HTMLSelectElement).value, options: Array.from((element as HTMLSelectElement).options).slice(0, 5).map(option => ({ value: option.value, text: option.textContent })) })) )));
        const category = page.locator('select').nth(categoryIndex);
        await expect(category).toBeVisible();
        const activeCategory = await category.locator('option').evaluateAll(options => options.find(option => option.value !== '')?.value);
        if (!activeCategory) throw new Error('No category target option available');
        await category.selectOption(activeCategory);

        const save = page.getByRole('button', { name: /Lưu cấu hình/i }).last();
        const saveAndReload = async () => {
            const request = page.waitForRequest(
                request => request.method() === 'POST' && /livewire-[^/]+\/update/.test(request.url()),
                { timeout: 15000 },
            );
            await page.locator('button[wire\\:click="saveSettings"]').last().click({ noWaitAfter: true, force: true });
            const livewireRequest = await request;
            const response = await livewireRequest.response();
            expect(response, `Livewire request had no response: ${livewireRequest.url()}`).not.toBeNull();
            expect(response?.status()).toBe(200);
            await page.goto('/', { waitUntil: 'commit' });
        };
        await saveAndReload();

        await page.goto('/', { waitUntil: 'commit' });
        await expect(page.locator('header nav')).toContainText('Sản phẩm kiểm thử');
        await expect(page.locator('header nav a').filter({ hasText: 'Sản phẩm kiểm thử' }).first()).toHaveAttribute('href', /\/danh-muc\//);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        const visibleCampaign = page.locator('[data-site-campaign]:not(.hidden)');
        if (await visibleCampaign.count()) {
            await visibleCampaign.locator('[data-site-campaign-close]').last().click({ force: true });
        }
        await page.getByRole('button', { name: 'Mở menu' }).click();
        await expect(page.locator('#mobile-menu')).toContainText('Sản phẩm kiểm thử');

        await page.goto('/admin/manage-settings');
        await page.locator('section').filter({ hasText: 'Điều hướng public' }).first().getByText('Điều hướng public', { exact: true }).click();
        const sortIndex = await inputs.evaluateAll(elements => elements.findIndex(element => (element as HTMLInputElement).value === '20'));
        if (sortIndex < 0) throw new Error('Navigation order input was not rendered');
        const sort = inputs.nth(sortIndex);
        await sort.fill('999');
        await saveAndReload();
        await expect(page.locator('header nav')).toContainText('Sản phẩm kiểm thử');

        await page.goto('/admin/manage-settings');
        await page.locator('section').filter({ hasText: 'Điều hướng public' }).first().getByText('Điều hướng public', { exact: true }).click();
        await page.waitForTimeout(3000);
        const activeToggle = page.getByRole('switch').first();
        if (await activeToggle.isChecked()) await activeToggle.uncheck();
        await saveAndReload();
        await expect(page.locator('header nav a').filter({ hasText: 'Sản phẩm kiểm thử' })).toHaveCount(0);

        await page.goto('/admin/manage-settings');
        await page.locator('section').filter({ hasText: 'Điều hướng public' }).first().getByText('Điều hướng public', { exact: true }).click();
        await inputs.nth(labelIndex).fill(originalLabel || 'Sản phẩm');
        const restoreActive = page.getByRole('switch').first();
        if (!(await restoreActive.isChecked())) await restoreActive.check();
        await saveAndReload();
        expect(errors, errors.join('\n')).toEqual([]);
    });
});
