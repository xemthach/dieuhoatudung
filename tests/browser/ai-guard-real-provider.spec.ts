import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const enabled = process.env.AI_GUARD_REAL_PROVIDER_TEST === '1';
const fixture = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/ai-guard-real-provider-fixture.php', mode], { cwd: process.cwd(), encoding: 'utf8' },
));

test.describe.serial('AI guard policy real-provider preflight', () => {
    test.skip(!enabled, 'Explicit real-provider certification only.');
    let state: any;
    test.beforeAll(() => { state = fixture('setup'); });
    test.afterAll(() => { fixture('cleanup'); });

    test('operator generates one governed draft with policy evidence and no Product mutation', async ({ page }) => {
        test.setTimeout(300_000);
        const errors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') errors.push(`console:${message.text()}`); });
        page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => errors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) errors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(state.operator.email);
        await page.locator('#form\\.password').fill(state.operator.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);
        await page.waitForLoadState('networkidle');
        await page.goto(`/admin/products/${state.product_id}/edit`);

        await page.getByRole('button', { name: 'Tạo nội dung AI', exact: true }).click();
        const dialog = page.locator('.fi-modal-window:visible').last();
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Gửi', exact: true }).click();

        let snapshot: any = fixture('snapshot');
        for (let attempt = 0; attempt < 120; attempt++) {
            if (['REVIEW_REQUIRED', 'BLOCKED', 'FAILED', 'DONE'].includes(snapshot.canonical_status ?? '')) break;
            await page.waitForTimeout(2000);
            snapshot = fixture('snapshot');
        }

        expect(snapshot.job_id).toBeTruthy();
        expect(snapshot.item_id).toBeTruthy();
        expect(snapshot.request_log_id).toBeTruthy();
        expect(snapshot.request_status).toBe('success');
        expect(snapshot.provider).toBeTruthy();
        expect(snapshot.model).toBeTruthy();
        expect(snapshot.tokens).toBeGreaterThan(0);
        expect(snapshot.draft_id).toBeTruthy();
        expect(snapshot.policy_version).toMatch(/^ai-guard-policy-v1:/);
        expect(snapshot.product_count).toBe(state.product_count_before);
        expect(snapshot.content_hash).toBe(state.content_hash_before);
        expect(snapshot.canonical_status).toBe('REVIEW_REQUIRED');
        expect(snapshot.validation_errors.filter((error: any) => error.severity === 'critical')).toEqual([]);
        expect(errors, errors.join('\n')).toEqual([]);
    });
});
