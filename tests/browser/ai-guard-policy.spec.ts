import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const fixture = (command: string): any => JSON.parse(execFileSync('php', ['tests/browser/ai-product-action-matrix-fixture.php', command], { encoding: 'utf8' }));

async function login(page: Page, user: any) {
    await page.context().clearCookies();
    await page.goto('/admin/login');
    await page.locator('#form\\.email').fill(user.email);
    await page.locator('#form\\.password').fill(user.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/\/admin(\/|$)/);
    await page.waitForLoadState('networkidle');
}

test.describe.serial('AI guard policy settings', () => {
    let state: any;
    test.beforeAll(() => { state = fixture('setup'); });
    test.afterAll(() => { fixture('cleanup'); });

    test('operator can configure editorial rule while mandatory rules remain locked', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('pageerror', error => errors.push(error.message));
        await login(page, state.operator);
        await page.goto('/admin/manage-settings');
        await page.getByRole('tab', { name: 'AI Guard Policy' }).click();

        const contentMode = page.locator('#settingsSchema\\.ai_guard_policy__CONTENT_TOO_SHORT');
        await expect(contentMode).toBeVisible();
        await expect(page.locator('#settingsSchema\\.ai_guard_locked_fact')).toBeDisabled();
        await contentMode.selectOption('IGNORE');
        await page.getByRole('button', { name: /Lưu cấu hình/i }).first().click();
        await expect(page.getByText(/Đã lưu cấu hình/i).first()).toBeVisible();

        await contentMode.selectOption('WARN');
        await page.getByRole('button', { name: /Lưu cấu hình/i }).first().click();
        await expect(page.getByText(/Đã lưu cấu hình/i).first()).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('non-operator cannot access or mutate global guard policy', async ({ page }) => {
        await login(page, state.users.none);
        const response = await page.goto('/admin/manage-settings');
        expect(response?.status()).toBe(403);
        await expect(page.locator('#settingsSchema\\.ai_guard_policy__CONTENT_TOO_SHORT')).toHaveCount(0);
    });
});
