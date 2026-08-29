import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

type Fixture = Record<string, any>;

const root = path.resolve(import.meta.dirname, '../..');
const evidenceDir = path.join(root, 'docs/reports/final/artifacts/browser');
let fixture: Fixture;
const runtimeIssues: string[] = [];

function fixtureCommand(mode: string): any {
    const stdout = execFileSync('php', ['tests/browser/fixture.php', mode], {
        cwd: root,
        env: process.env,
        encoding: 'utf8',
    });

    return JSON.parse(stdout);
}

function observe(page: Page): void {
    page.on('console', message => {
        if (message.type() === 'error') runtimeIssues.push(`console:${message.text()}`);
    });
    page.on('pageerror', error => runtimeIssues.push(`pageerror:${error.message}`));
    page.on('requestfailed', request => {
        const url = request.url();
        if (url.startsWith(process.env.BROWSER_BASE_URL ?? 'http://127.0.0.1:8098')) {
            runtimeIssues.push(`requestfailed:${request.method()}:${url}:${request.failure()?.errorText}`);
        }
    });
    page.on('response', response => {
        if (response.url().includes('/livewire/update') && response.status() >= 400) {
            runtimeIssues.push(`livewire:${response.status()}:${response.url()}`);
        }
    });
}

async function login(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.locator('#form\\.email').fill(fixture.email);
    await page.locator('#form\\.password').fill(fixture.password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin(?:\/)?$/);
}

async function screenshot(page: Page, name: string, fullPage = false): Promise<void> {
    await page.screenshot({ path: path.join(evidenceDir, name), fullPage });
}

test.describe.serial('Marketing/content browser certification', () => {
    test.beforeAll(() => {
        fs.mkdirSync(evidenceDir, { recursive: true });
        fixture = fixtureCommand('setup');
    });

    test.afterAll(() => {
        fs.writeFileSync(path.join(evidenceDir, 'runtime-issues.json'), JSON.stringify(runtimeIssues, null, 2));
        fixtureCommand('cleanup');
    });

    test.beforeEach(async ({ page }) => {
        observe(page);
        await page.route('https://dieuhoa-tudung.test/**', async route => {
            const url = new URL(route.request().url());
            const base = process.env.BROWSER_BASE_URL ?? 'http://127.0.0.1:8098';
            const response = await route.fetch({ url: `${base}${url.pathname}${url.search}` });
            await route.fulfill({ response });
        });
        await login(page);
    });

    test('website campaign production, schedule, preview, image and video renderers', async ({ page }) => {
        await page.goto('/');
        const active = page.locator(`[data-campaign-id="${fixture.active_campaign_id}"]`);
        await expect(active.locator('[role="dialog"]')).toBeVisible();
        await expect(active).toContainText('Campaign Browser Active');
        await expect(page.getByText('Campaign Browser Inactive')).toHaveCount(0);
        await expect(page.getByText('Campaign Browser Future')).toHaveCount(0);
        await screenshot(page, 'campaign-production-desktop.png');

        const before = fixtureCommand('snapshot').preview_events;
        await page.goto(`/admin/site-campaigns/${fixture.preview_campaign_id}/edit`);
        await page.getByRole('button', { name: /Xem trước campaign/i }).click();
        const preview = page.locator('[data-campaign-preview="1"]');
        await expect(preview).toBeVisible();
        await expect(preview).toContainText('Campaign Browser Preview');
        await screenshot(page, 'campaign-preview-inactive.png');
        await page.getByRole('button', { name: /Đóng|Close/i }).last().click();
        expect(fixtureCommand('snapshot').preview_events).toBe(before);

        await page.goto(`/admin/site-campaigns/${fixture.image_campaign_id}/edit`);
        await page.getByRole('button', { name: /Xem trước campaign/i }).click();
        await expect(page.locator('[data-campaign-preview="1"] img')).toBeVisible();
        await screenshot(page, 'campaign-preview-image.png');

        await page.goto(`/admin/site-campaigns/${fixture.video_campaign_id}/edit`);
        await page.getByRole('button', { name: /Xem trước campaign/i }).click();
        await expect(page.locator('[data-campaign-preview="1"] iframe')).toHaveAttribute('src', /youtube\.com\/embed/);
        await screenshot(page, 'campaign-preview-video.png');
    });

    test('AI Post review/apply keeps identity and is idempotent', async ({ page }) => {
        const before = fixtureCommand('snapshot');
        await page.goto(`/admin/posts/${fixture.post_id}/edit`);

        await page.getByRole('button', { name: /So sánh bản nháp AI/i }).click();
        await expect(page.getByText('Nội dung AI đã kiểm duyệt')).toBeVisible();
        await screenshot(page, 'post-ai-preview.png');
        await page.getByRole('button', { name: /Đóng|Close/i }).last().click();

        await page.getByRole('button', { name: /Duyệt bản nháp AI/i }).click();
        await page.getByRole('button', { name: /Xác nhận|Confirm|Yes/i }).last().click();
        await expect(page.getByRole('button', { name: /Chèn nội dung AI/i })).toBeVisible();
        await page.getByRole('button', { name: /Chèn nội dung AI/i }).click();
        await page.getByRole('button', { name: /Xác nhận|Confirm|Yes/i }).last().click();
        await expect(page).toHaveURL(new RegExp(`/admin/posts/${fixture.post_id}/edit`));

        const after = fixtureCommand('snapshot');
        expect(after.post_id).toBe(before.post_id);
        expect(after.post_count).toBe(before.post_count);
        expect(after.post_content).toContain('Nội dung AI đã kiểm duyệt');
        expect(after.job_payload.applied_at).toBeTruthy();

        if (await page.getByRole('button', { name: /Chèn nội dung AI/i }).isVisible()) {
            await page.getByRole('button', { name: /Chèn nội dung AI/i }).click();
            await page.getByRole('button', { name: /Xác nhận|Confirm|Yes/i }).last().click();
        }
        const doubleApply = fixtureCommand('snapshot');
        expect(doubleApply.post_id).toBe(before.post_id);
        expect(doubleApply.post_count).toBe(before.post_count);
        await screenshot(page, 'post-ai-applied-same-record.png', true);
    });

    test('Post RichEditor supports pointer, cursor, mouse selection, typing, delete, paste, toolbar and persistence', async ({ page, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        await page.goto(`/admin/posts/${fixture.post_id}/edit`);
        const editor = page.locator('[contenteditable="true"]').first();
        await expect(editor).toBeVisible();
        await editor.scrollIntoViewIfNeeded();

        const box = await editor.boundingBox();
        expect(box).not.toBeNull();
        const hitTarget = await page.evaluate(({ x, y }) => {
            const el = document.elementFromPoint(x, y);
            return el ? { tag: el.tagName, editable: el.closest('[contenteditable="true"]') !== null, pointer: getComputedStyle(el).pointerEvents } : null;
        }, { x: box!.x + box!.width / 2, y: box!.y + Math.min(40, box!.height / 2) });
        expect(hitTarget?.editable).toBe(true);
        expect(hitTarget?.pointer).not.toBe('none');

        await editor.click();
        expect(await editor.evaluate(el => el.contains(document.activeElement))).toBe(true);
        await page.keyboard.press('Control+End');
        await page.keyboard.type(' Văn bản tiếng Việt để sửa.');
        await page.keyboard.press('Backspace');
        await page.keyboard.type('!');

        await page.mouse.move(box!.x + 22, box!.y + 28);
        await page.mouse.down();
        await page.mouse.move(box!.x + Math.min(210, box!.width - 20), box!.y + 28, { steps: 12 });
        await page.mouse.up();
        const selected = await page.evaluate(() => window.getSelection()?.toString() ?? '');
        expect(selected.length).toBeGreaterThan(0);

        const bold = page.getByRole('button', { name: /^(In đậm|Bold)$/i });
        if (await bold.count()) {
            await bold.click();
        } else {
            throw new Error('RichEditor bold toolbar button was not found');
        }

        await editor.click();
        await page.keyboard.press('Control+End');
        await page.evaluate(() => navigator.clipboard.writeText(' Nội dung dán an toàn.'));
        await page.keyboard.press('Control+V');
        await expect(editor).toContainText('Nội dung dán an toàn');
        await screenshot(page, 'post-editor-focused-editable.png');

        await page.locator('button[type="submit"]').filter({ hasText: /Lưu|Save/i }).last().click();
        await page.waitForLoadState('networkidle');
        await page.reload();
        const reloaded = page.locator('[contenteditable="true"]').first();
        await expect(reloaded).toContainText('Nội dung dán an toàn');
        await expect(reloaded).toContainText('Văn bản tiếng Việt để sửa');
        await expect(reloaded.locator('script')).toHaveCount(0);
        const persistedHtml = fixtureCommand('snapshot').post_content as string;
        expect(persistedHtml).not.toMatch(/<script|onclick=|contenteditable=|position\s*:\s*fixed|javascript:/i);
    });

    test('Promotion banner, landing, popup and announcement render responsively', async ({ page }) => {
        await page.setViewportSize({ width: 1366, height: 900 });
        await page.goto('/');
        await expect(page.locator(`[data-promotion-id="${fixture.promotion_banner_id}"]`)).toBeVisible();
        const popup = page.locator(`[data-promotion-id="${fixture.promotion_popup_id}"]`);
        await expect(popup).toBeVisible();
        await expect(page.locator(`[data-promotion-id="${fixture.promotion_announcement_id}"]`)).toBeVisible();
        await screenshot(page, 'promotion-banner-popup-desktop.png');
        const campaignDialog = page.locator(`[data-campaign-id="${fixture.active_campaign_id}"]`);
        if (await campaignDialog.locator('[data-site-campaign-close]').last().isVisible()) {
            await campaignDialog.locator('[data-site-campaign-close]').last().click();
        }
        await popup.locator('[data-promotion-close]').click();
        await expect(popup).toHaveCount(0);

        await page.goto('/dieu-hoa-tu-dung');
        await expect(page.locator(`[data-promotion-id="${fixture.promotion_landing_id}"]`)).toBeVisible();
        await screenshot(page, 'promotion-landing-desktop.png');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        await expect(page.locator(`[data-promotion-id="${fixture.promotion_banner_id}"]`)).toBeVisible();
        await expect(page.locator(`[data-promotion-id="${fixture.promotion_popup_id}"]`)).toBeVisible();
        await screenshot(page, 'promotion-campaign-mobile-390.png');
    });

    test('Promotion AI fills description and detailed content without changing structured facts', async ({ page }) => {
        const before = fixtureCommand('snapshot').promotion;
        await page.goto(`/admin/promotions/${fixture.promotion_ai_id}/edit`);
        await page.getByRole('button', { name: /Generate bằng AI/i }).click();
        await page.getByRole('button', { name: /Generate & preview trong form/i }).click();

        const description = page.getByLabel(/Mô tả chương trình/i);
        const content = page.getByLabel(/Nội dung chi tiết/i);
        await expect(description).not.toHaveValue('');
        await expect(content).not.toHaveValue('');
        await screenshot(page, 'promotion-ai-description-detailed-content.png', true);

        await page.locator('button[type="submit"]').filter({ hasText: /Lưu|Save/i }).last().click();
        await page.waitForLoadState('networkidle');
        await page.reload();
        await expect(description).not.toHaveValue('');
        await expect(content).not.toHaveValue('');

        const after = fixtureCommand('snapshot').promotion;
        expect(after.id).toBe(before.id);
        expect(after.discount_type).toBe(before.discount_type);
        expect(after.discount_value).toBe(before.discount_value);
        expect(after.start_at).toBe(before.start_at);
        expect(after.end_at).toBe(before.end_at);
        expect(after.scope).toBe(before.scope);
        expect(after.description).toBeTruthy();
        expect(after.content).toBeTruthy();
    });

    test('no relevant application console, page, Livewire or network errors', async () => {
        expect(runtimeIssues).toEqual([]);
    });
});
