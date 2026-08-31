import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const enabled = process.env.AI_PRODUCT_REAL_PROVIDER_TEST === '1';
const root = process.cwd();
const targetProduct = process.env.AI_PRODUCT_TEST_ID ?? '1319';

type Fixture = {
    email: string;
    password: string;
    product_id: number;
    product_count_before: number;
    product_content_hash_before: string;
    latest_item_before: number | null;
};

type Snapshot = {
    product_count: number;
    product_content_hash: string;
    job_id: number | null;
    item_id: number | null;
    item_status: string | null;
    canonical_status: string | null;
    provider: string | null;
    model: string | null;
    tokens: number | null;
    draft_id: number | null;
    draft_status: string | null;
    draft_content_length: number;
};

const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php',
    ['tests/browser/ai-product-fixture.php', mode, targetProduct],
    { cwd: root, encoding: 'utf8' },
));

test.describe.serial('AI Product real-provider review workflow', () => {
    test.skip(!enabled, 'Set AI_PRODUCT_REAL_PROVIDER_TEST=1 for the explicitly authorized provider test.');
    let fixture: Fixture;

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('generates a persisted review draft without mutating Product content', async ({ page }) => {
        test.setTimeout(240_000);
        const runtimeErrors: string[] = [];
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

        const generate = page.getByRole('button', { name: /Tạo (lại )?nội dung AI/i }).first();
        await expect(generate).toBeVisible();
        await generate.click();
        const submit = page.getByRole('dialog').getByRole('button', { name: 'Gửi', exact: true });
        await expect(submit).toBeVisible();
        await submit.click();

        let snapshot: Snapshot = fixtureCommand('snapshot');
        for (let attempt = 0; attempt < 90; attempt++) {
            if ((snapshot.item_id ?? 0) > (fixture.latest_item_before ?? 0)
                && ['needs_review', 'completed', 'completed_verified', 'completed_with_warnings', 'failed', 'blocked'].includes(snapshot.item_status ?? '')) {
                break;
            }
            await page.waitForTimeout(2000);
            snapshot = fixtureCommand('snapshot');
        }

        expect(snapshot.item_id ?? 0).toBeGreaterThan(fixture.latest_item_before ?? 0);
        expect(snapshot.item_status).toBe('needs_review');
        expect(snapshot.canonical_status).toBe('REVIEW_REQUIRED');
        expect(snapshot.provider).toBeTruthy();
        expect(snapshot.model).toBeTruthy();
        expect(snapshot.tokens ?? 0).toBeGreaterThan(0);
        expect(snapshot.draft_id).toBeTruthy();
        expect(snapshot.draft_status).toBe('needs_review');
        expect(snapshot.draft_content_length).toBeGreaterThan(0);
        expect(snapshot.product_count).toBe(fixture.product_count_before);
        expect(snapshot.product_content_hash).toBe(fixture.product_content_hash_before);

        await page.reload();
        const preview = page.getByRole('button', { name: 'Xem bản nháp AI' });
        await expect(preview).toBeVisible();
        await preview.click();
        await expect(page.getByText('Content HTML')).toBeVisible();
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
