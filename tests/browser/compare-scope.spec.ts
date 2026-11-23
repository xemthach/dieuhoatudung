import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

test('product detail adds through the canonical comparison contract', async ({ page }) => {
    await page.goto('/san-pham/filter-alpha');
    await page.locator('button[onclick="addToCompare(\'filter-alpha\')"]').click();
    const bar = page.locator('[x-data="compareBar()"]');
    await expect(bar).toBeVisible();
    await bar.getByRole('link', { name: /So sánh ngay/ }).click();
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
    await page.locator('[data-compare-table]').getByRole('button', { name: 'Xóa Filter Alpha khỏi so sánh' }).click();
    await expect(page.locator('[data-compare-empty]')).toBeVisible();
    await page.getByRole('link', { name: 'Xem danh sách sản phẩm' }).click();
    await expect(page).toHaveURL(/\/san-pham$/);
});

test('pending and failed removal preserve the table and restore controls', async ({ page }) => {
    await page.goto('/so-sanh-san-pham?products=filter-alpha,filter-beta');
    const originalUrl = page.url();
    let finish!: () => void;
    const gate = new Promise<void>(resolve => { finish = resolve; });
    let requests = 0;
    await page.route('**/so-sanh-san-pham/remove', async route => {
        requests++;
        await gate;
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ success: false }) });
    });
    const button = page.locator('[data-compare-table]').getByRole('button', { name: 'Xóa Filter Alpha khỏi so sánh' });
    await button.click();
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await button.evaluate(element => (element as HTMLButtonElement).click());
    finish();
    await expect(page.locator('[data-compare-error]')).toBeVisible();
    await expect(button).toBeEnabled();
    await expect(page.locator('[data-compare-count]')).toHaveText('2');
    await expect(page).toHaveURL(originalUrl);
    expect(requests).toBe(1);
});

test('compare remove and clear update the page without navigating to JSON', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`); });
    await page.goto('/so-sanh-san-pham?products=filter-alpha,filter-beta');
    await expect(page.locator('[data-compare-count]')).toHaveText('2');
    await page.locator('[data-compare-table]').getByRole('button', { name: 'Xóa Filter Alpha khỏi so sánh' }).click();
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
    await expect(page.locator('[data-compare-count]')).toHaveText('1');
    await expect(page.locator('[data-compare-product="filter-alpha"]')).toHaveCount(0);
    await expect(page.locator('[data-compare-product="filter-beta"]').first()).toBeVisible();
    await page.locator('[data-compare-table]').getByRole('button', { name: 'Xóa Filter Beta khỏi so sánh' }).click();
    await expect(page.locator('[data-compare-empty]')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('"success"');

    await page.goto('/so-sanh-san-pham?products=filter-alpha,filter-beta');
    await page.getByRole('button', { name: 'Xóa tất cả', exact: true }).first().click();
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
    await expect(page.locator('[data-compare-empty]')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Xem danh sách sản phẩm' })).toHaveAttribute('href', /\/san-pham$/);
    expect(errors).toEqual([]);
});

test('compare remove without JavaScript redirects back to the comparison page', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8098/so-sanh-san-pham?products=filter-alpha');
    await page.getByRole('button', { name: 'Xóa Filter Alpha khỏi so sánh' }).click();
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
    await expect(page.locator('[data-compare-empty]')).toBeVisible();
    await page.goto('http://127.0.0.1:8098/so-sanh-san-pham?products=filter-alpha,filter-beta');
    await page.getByRole('button', { name: 'Xóa tất cả', exact: true }).first().click();
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
    await expect(page.locator('[data-compare-empty]')).toBeVisible();
    await context.close();
});

test('public directory, compare, quote and legacy pricing routes respond', async ({ request }) => {
    const paths = ['/', '/san-pham', '/so-sanh-san-pham', '/thuong-hieu', '/dai-ly', '/blog', '/bao-gia', '/bang-gia', '/bang-gia/dieu-hoa-tu-dung', '/tim-kiem?q=may', '/faq', '/faq/dieu-hoa-tu-dung'];
    for (const path of paths) {
        const response = await request.get(path);
        expect(response.status(), `${path} returned ${response.status()}`).toBe(200);
    }
});

test('removing a product from the sticky bar updates the comparison table', async ({ page }) => {
    await page.goto('/so-sanh-san-pham?products=filter-alpha,filter-beta');
    const bar = page.locator('[x-data="compareBar()"]');
    await expect(bar).toBeVisible();
    await bar.locator('[title="Filter Alpha"] button[title="Xóa"]').click();
    await expect(page.locator('[data-compare-product="filter-alpha"]')).toHaveCount(0);
    await expect(page.locator('[data-compare-count]')).toHaveText('1');
    await expect(page).toHaveURL(/\/so-sanh-san-pham$/);
});

test('primary category changes the compare heading and product destination', async ({ page }) => {
    const fixture = JSON.parse(execFileSync('php', ['tests/browser/compare-scope-fixture.php', 'enable'], { encoding: 'utf8' }));
    try {
        await page.goto('/so-sanh-san-pham');
        await expect(page.getByRole('heading', { name: `So sánh ${fixture.name}` })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Xem danh sách sản phẩm' })).toHaveAttribute('href', fixture.url);
        await page.goto('/');
        await expect(page.getByRole('heading', { name: `Cần tư vấn ${fixture.name}?` })).toBeVisible();
    } finally {
        execFileSync('php', ['tests/browser/compare-scope-fixture.php', 'disable'], { encoding: 'utf8' });
    }
});

test('public internal links resolve without 404 or 500', async ({ page, request }) => {
    test.setTimeout(180_000);
    const paths = new Set<string>();
    for (const source of ['/', '/san-pham', '/san-pham/filter-alpha', '/thuong-hieu', '/dai-ly', '/blog', '/bao-gia', '/bang-gia', '/faq', '/so-sanh-san-pham']) {
        await page.goto(source);
        const found = await page.locator('a[href]').evaluateAll(links => links.map(link => {
            const url = new URL((link as HTMLAnchorElement).href, location.href);
            return url.origin === location.origin ? url.pathname : '';
        }).filter(path => path && !/^\/(admin|livewire|storage|build)\b/.test(path) && !path.includes('/export/')));
        found.forEach(path => paths.add(path));
    }
    expect(paths.size).toBeGreaterThan(10);
    const results: { path: string; status: number }[] = [];
    for (const path of [...paths].slice(0, 180)) {
        const response = await request.get(path, { maxRedirects: 0 });
        results.push({ path, status: response.status() });
        expect(response.status(), `${path} returned ${response.status()}`).toBeLessThan(400);
        if (response.status() >= 300) expect((await request.get(path)).status()).toBe(200);
    }
    writeFileSync('storage/logs/url-scope-crawl-isolated.json', JSON.stringify(results, null, 2));
});
