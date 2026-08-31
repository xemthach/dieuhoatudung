import { expect, test } from '@playwright/test';

const enabled = process.env.AI_LIVE_PARITY_TEST === '1';
const credentials = (): { email: string; password: string; expected_version: string } => {
    const email = process.env.LIVE_ADMIN_EMAIL ?? '';
    const password = process.env.LIVE_ADMIN_PASSWORD ?? '';
    const expected_version = process.env.LIVE_EXPECTED_VERSION ?? '';
    if (!email || !password || !expected_version) throw new Error('LIVE_PARITY_CREDENTIALS_OR_VERSION_MISSING');

    return { email, password, expected_version };
};

test.describe('AI Product Live parity read-only smoke', () => {
    test.skip(!enabled, 'Explicit authenticated Live parity check only.');

    test('operator sees aligned web/worker runtime and Product 1320 without mutation', async ({ page }, testInfo) => {
        const auth = credentials();
        const errors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') errors.push(`console:${message.text()}`); });
        page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => errors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) errors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(auth.email);
        await page.locator('#form\\.password').fill(auth.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);

        await page.goto('/admin/a-i-queue-health');
        await expect(page.getByText('Trạng thái vận hành AI')).toBeVisible();
        const health = await page.locator('body').innerText();
        expect(health).toContain(`Web\nv${auth.expected_version}`);
        expect(health).toContain(`Worker\nv${auth.expected_version}`);
        expect(health).toContain('Đã cập nhật');

        await page.goto('/admin/products/1320/edit');
        await expect(page.getByRole('heading', { name: /Daikin FTKF/i })).toBeVisible();
        await expect(page.getByText('Nội dung AI', { exact: true })).toBeVisible();

        await testInfo.attach('live-parity-sanitized.json', {
            body: JSON.stringify({
                expected_version: auth.expected_version,
                worker_up_to_date: health.includes('Đã cập nhật'),
                product_id: 1320,
                mutation: false,
            }, null, 2),
            contentType: 'application/json',
        });
        expect(errors, errors.join('\n')).toEqual([]);
    });
});
