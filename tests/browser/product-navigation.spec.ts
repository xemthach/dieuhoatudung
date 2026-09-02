import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();

const errors: string[] = [];

test.describe('Product listing and public navigation certification', () => {
    test.beforeAll(() => {
        execFileSync('php', ['tests/browser/fixture.php', 'setup'], { cwd: root, encoding: 'utf8' });
    });

    test.afterAll(() => {
        execFileSync('php', ['tests/browser/fixture.php', 'cleanup'], { cwd: root, encoding: 'utf8' });
    });

    test.beforeEach(async ({ page }) => {
        page.on('console', message => { if (message.type() === 'error') errors.push(`console:${message.text()}`); });
        page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => errors.push(`requestfailed:${request.url()}:${request.failure()?.errorText}`));
        page.on('response', response => {
            if (response.status() >= 500) errors.push(`http:${response.status()}:${response.url()}`);
        });
    });

    test('desktop listing, filters, sort, pagination and product detail', async ({ page }) => {
        await page.setViewportSize({ width: 1366, height: 900 });
        await page.goto('/san-pham');
        await expect(page.locator('article[id^="product-card-"]').first()).toBeVisible();
        await expect(page.locator('header nav')).toContainText('Sản phẩm');

        const category = page.locator('a[href*="/danh-muc/"]').filter({ hasText: /Cassette/i }).first();
        await expect(category).toBeVisible();
        await category.click();
        await expect(page).toHaveURL(/danh-muc\//);
        await expect(page.locator('article[id^="product-card-"]').first()).toBeVisible();

        await page.goto('/san-pham');
        const daikin = page.locator('label').filter({ hasText: /Daikin/i }).locator('input[name="brand[]"]').first();
        await expect(daikin).toBeVisible();
        await daikin.check();
        await expect(page).toHaveURL(/brand%5B%5D=|brand\[\]=/);
        await expect(page.locator('article[id^="product-card-"]').first()).toBeVisible();

        await page.goto('/san-pham');
        const btu = page.locator('input[name="btu[]"]').first();
        await btu.check();
        await expect(page).toHaveURL(/btu%5B%5D=|btu\[\]=/);
        await page.selectOption('select', 'price_asc');
        await expect(page).toHaveURL(/sort=price_asc/);

        for (const capacity of ['18000', '48000']) {
            const response = await page.goto(`/san-pham?btu[]=${capacity}`);
            expect(response?.status(), `BTU ${capacity}`).toBeLessThan(400);
            await expect(page.locator('article[id^="product-card-"]').first()).toBeVisible();
        }

        const multiResponse = await page.goto('/san-pham?btu[]=18000&btu[]=48000');
        expect(multiResponse?.status(), 'multi-BTU').toBeLessThan(400);
        await expect(page.locator('article[id^="product-card-"]').first()).toBeVisible();

        await page.goto('/san-pham?page=2');
        const pageTwo = page.locator('a[aria-current="page"]');
        if (await pageTwo.count()) await expect(pageTwo).toContainText('2');

        await page.goto('/san-pham');
        const productLink = page.locator('article[id^="product-card-"] a[href*="/san-pham/"]').first();
        await expect(productLink).toBeVisible();
        await productLink.click();
        await expect(page).toHaveURL(/\/san-pham\/[^/]+$/);
        await expect(page.locator('main')).toBeVisible();
        const productCategoryLink = page.locator('main a[href*="/danh-muc/"]').first();
        if (await productCategoryLink.count()) {
            const categoryUrl = await productCategoryLink.getAttribute('href');
            expect(categoryUrl).toBeTruthy();
            const categoryResponse = await page.request.get(categoryUrl!);
            expect(categoryResponse.status(), categoryUrl!).toBeLessThan(400);
        }
    });

    test('desktop and mobile consume the same safe navigation targets', async ({ page, request }) => {
        await page.setViewportSize({ width: 1366, height: 900 });
        await page.goto('/');
        const desktopTargets = await page.locator('header nav a').evaluateAll(anchors => anchors.map(a => ({ text: a.textContent?.trim(), href: (a as HTMLAnchorElement).href })).filter(x => x.text));
        expect(desktopTargets.some(x => x.href.endsWith('/san-pham'))).toBe(true);
        for (const item of desktopTargets.filter(x => x.href.startsWith(new URL(process.env.BROWSER_BASE_URL ?? 'http://127.0.0.1:8098').origin))) {
            const response = await request.get(item.href);
            expect(response.status(), `${item.text} ${item.href}`).toBeLessThan(400);
        }

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        const visibleCampaign = page.locator('[data-site-campaign]:not(.hidden)');
        if (await visibleCampaign.count()) await visibleCampaign.locator('[data-site-campaign-close]').last().click({ force: true });
        await page.locator('button[aria-label="Mở menu"]').click();
        const mobile = page.locator('#mobile-menu');
        await expect(mobile).toBeVisible();
        await expect(mobile).toContainText('Sản phẩm');
        await expect(mobile).toContainText('Bảng giá');
        await expect(mobile).toContainText('Blog');
        const mobileProducts = mobile.locator('a[href$="/san-pham"]');
        await expect(mobileProducts).toHaveCount(1);
    });

    test.afterAll(() => expect(errors, errors.join('\n')).toEqual([]));
});
