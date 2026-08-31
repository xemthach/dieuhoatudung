import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';

const root = process.cwd();
type State = {
    operator: { id: number; email: string; password: string };
    users: Record<string, { id: number; email: string; password: string }>;
    products: Record<string, { product_id: number; model_code: string; apply_confirmation: string; draft_id?: number; item_id?: number; job_id?: number; content_hash_before: string }>;
    product_count_before: number;
};

const fixture = (mode: 'setup' | 'snapshot' | 'cleanup', key?: string): any => JSON.parse(execFileSync(
    'php',
    ['tests/browser/ai-product-action-matrix-fixture.php', mode, ...(key ? [key] : [])],
    { cwd: root, encoding: 'utf8' },
));

const runtimeErrors: string[] = [];
const watchRuntime = (page: Page) => {
    page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
    page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
    page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
    page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });
};

const login = async (page: Page, actor: State['users'][string]) => {
    await page.context().clearCookies();
    await page.goto('/admin/login');
    await page.locator('#form\\.email').fill(actor.email);
    await page.locator('#form\\.password').fill(actor.password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin(?:\/)?$/);
};

const openProduct = async (page: Page, state: State, key: string) => {
    await page.goto(`/admin/products/${state.products[key].product_id}/edit`);
    await expect(page.getByRole('heading', { name: /\[AI MATRIX\]/i })).toBeVisible();
};

const dialogSubmit = async (page: Page, name: RegExp) => {
    const dialog = page.locator('.fi-modal-window:visible').last();
    await expect(dialog).toBeVisible();
    const button = dialog.getByRole('button', { name }).last();
    await expect(button).toBeVisible();
    await button.click();
    await expect(dialog).toBeHidden({ timeout: 20_000 });
};

const openMore = async (page: Page) => {
    await page.getByRole('button', { name: 'More' }).last().click();
    await expect(page.locator('.fi-dropdown-panel:visible').last()).toBeVisible();
};

const moreItem = (page: Page, name: string) => page
    .locator('.fi-dropdown-panel:visible')
    .last()
    .getByText(name, { exact: true });

test.describe.serial('AI Product browser action and RBAC certification', () => {
    test.use({ viewport: { width: 1920, height: 1080 } });
    let state: State;

    test.beforeAll(() => { state = fixture('setup'); });
    test.afterAll(() => { fixture('cleanup'); });
    test.beforeEach(({ page }) => watchRuntime(page));

    test('preview exposes generated fields and leaves Product unchanged', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'preview');
        await page.getByRole('button', { name: 'Xem bản nháp' }).click();
        await expect(page.locator('.fi-modal-window:visible').getByText('Nội dung dài', { exact: true })).toBeVisible({ timeout: 30_000 });
        await expect(page.locator('.fi-modal-window:visible').getByText('AI Matrix SEO title')).toBeVisible();
        expect(fixture('snapshot', 'preview').content_hash).toBe(state.products.preview.content_hash_before);
    });

    test('approve without warning records actor and does not mutate Product', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'approve');
        await page.getByRole('button', { name: 'Duyệt' }).click();
        await dialogSubmit(page, /Duyệt|Xác nhận/);
        const row = fixture('snapshot', 'approve');
        expect(row.approval_status).toBe('APPROVED_FOR_APPLY');
        expect(row.approved_by).toBe(state.operator.id);
        expect(row.approved_at).toBeTruthy();
        expect(row.warning_override).toBe(false);
        expect(row.content_hash).toBe(state.products.approve.content_hash_before);
        await page.reload();
        await expect(page.getByText('Đã duyệt').first()).toBeVisible();
    });

    test('approve with warning requires confirmation and preserves warning evidence', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'warning');
        await page.getByRole('button', { name: 'Duyệt kèm cảnh báo' }).click();
        await expect(page.locator('.fi-modal-window:visible').getByText(/content_too_short:459\/800/).first()).toBeVisible();
        await dialogSubmit(page, /Duyệt kèm cảnh báo|Xác nhận/);
        const row = fixture('snapshot', 'warning');
        expect(row.approval_status).toBe('APPROVED_FOR_APPLY');
        expect(row.warning_override).toBe(true);
        expect(row.warnings_at_approval).toEqual(['content_too_short:459/800', 'missing_h2_h3']);
        expect(row.review_note).toContain('[WARNING_OVERRIDE]');
        expect(row.content_hash).toBe(state.products.warning.content_hash_before);
    });

    test('reject requires reason and records authenticated actor', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'reject');
        await openMore(page);
        await moreItem(page, 'Từ chối').click();
        const dialog = page.locator('.fi-modal-window:visible').last();
        await dialog.getByLabel('Lý do từ chối').fill('Không phù hợp định hướng biên tập');
        await dialogSubmit(page, /Từ chối/);
        const row = fixture('snapshot', 'reject');
        expect(row.approval_status).toBe('REJECTED');
        expect(row.rejected_by).toBe(state.operator.id);
        expect(row.rejected_at).toBeTruthy();
        expect(row.review_note).toContain('Không phù hợp');
        expect(row.content_hash).toBe(state.products.reject.content_hash_before);
        await page.reload();
        await expect(page.getByText('Đã từ chối').first()).toBeVisible();
    });

    test('discard is logical and records authenticated actor', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'discard');
        await openMore(page);
        await moreItem(page, 'Loại bỏ').click();
        const dialog = page.locator('.fi-modal-window:visible').last();
        await dialog.getByLabel('Lý do loại bỏ').fill('Loại bỏ fixture theo quy trình chứng nhận');
        await dialogSubmit(page, /Loại bỏ|Xác nhận/);
        const row = fixture('snapshot', 'discard');
        expect(row.approval_status).toBe('DISCARDED');
        expect(row.draft_status).toBe('discarded');
        expect(row.discarded_by).toBe(state.operator.id);
        expect(row.discarded_at).toBeTruthy();
        expect(row.content_hash).toBe(state.products.discard.content_hash_before);
        await page.reload();
        await expect(page.getByText('Đã loại bỏ').first()).toBeVisible();
    });

    test('apply mutates only the same Product and double apply is unavailable', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'apply');
        const before = fixture('snapshot', 'apply');
        await page.getByRole('button', { name: 'Áp dụng' }).click();
        const applyDialog = page.locator('.fi-modal-window:visible').last();
        await expect(applyDialog.getByText('Bản nháp AI sẽ cập nhật các trường sau')).toBeVisible();
        await applyDialog.getByLabel('Mã xác nhận áp dụng').fill(state.products.apply.apply_confirmation);
        await dialogSubmit(page, /Xác nhận và áp dụng/);
        const after = fixture('snapshot', 'apply');
        expect(after.product_id).toBe(before.product_id);
        expect(after.product_count).toBe(before.product_count);
        expect(after.content_hash).not.toBe(before.content_hash);
        expect(after.approval_status).toBe('APPLIED');
        expect(after.applied_by).toBe(state.operator.id);
        expect(after.long_description).toContain('Nội dung được tạo');
        expect(after.seo_title).toBe('AI Matrix SEO title');
        expect(after.merchant_title).toBe('AI Matrix Merchant title');
        expect(after.faq_count).toBe(1);
        await page.reload();
        await expect(page.getByRole('button', { name: 'Áp dụng' })).toHaveCount(0);
        await page.getByRole('tab', { name: 'Nội dung' }).click();
        const editor = page.locator('[contenteditable="true"]').first();
        await expect(editor).toContainText('Nội dung được tạo');
        await editor.click();
        await editor.press('End');
        await editor.pressSequentially(' Kiểm thử chỉnh sửa sau Apply.');
        await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
        await page.waitForTimeout(1_500);
        await page.reload();
        await page.getByRole('tab', { name: 'Nội dung' }).click();
        await expect(page.locator('[contenteditable="true"]').first()).toContainText('Kiểm thử chỉnh sửa sau Apply');
    });

    test('stale Product content blocks apply without overwrite', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'stale');
        const before = fixture('snapshot', 'stale');
        await expect(page.getByRole('button', { name: 'Áp dụng' })).toHaveCount(0);
        await page.getByRole('button', { name: 'Xem lý do bị chặn' }).click();
        await expect(page.locator('.fi-modal-window:visible').getByText('Sản phẩm đã được chỉnh sửa sau khi AI tạo bản nháp.')).toBeVisible();
        const after = fixture('snapshot', 'stale');
        expect(after.content_hash).toBe(before.content_hash);
        expect(after.applied_at).toBeNull();
    });

    test('duplicate generate points to existing draft and creates no job spam', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'duplicate');
        const before = fixture('snapshot', 'duplicate');
        await expect(page.getByRole('button', { name: 'Tạo nội dung AI' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toBeVisible();
        const after = fixture('snapshot', 'duplicate');
        expect(after.latest_job_id).toBe(before.latest_job_id);
        expect(after.latest_item_id).toBe(before.latest_item_id);
    });

    test('configured operator generates through ai_governed without mutating Product before review', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, state.operator);
        await openProduct(page, state, 'no_draft');
        const before = fixture('snapshot', 'no_draft');
        await page.getByRole('button', { name: 'Tạo nội dung AI' }).click();
        await dialogSubmit(page, /Gửi/);

        let after = fixture('snapshot', 'no_draft');
        for (let i = 0; i < 180 && ! ['needs_review', 'failed', 'blocked'].includes(after.latest_item_status); i++) {
            await page.waitForTimeout(1_000);
            after = fixture('snapshot', 'no_draft');
        }

        expect(['needs_review', 'failed', 'blocked']).toContain(after.latest_item_status);
        expect(after.latest_item_id).toBeTruthy();
        expect(after.product_id).toBe(before.product_id);
        expect(after.product_count).toBe(before.product_count);
        expect(after.content_hash).toBe(before.content_hash);
    });

    test('regenerate preserves old draft and creates a new operation', async ({ page }) => {
        test.setTimeout(180_000);
        await login(page, state.operator);
        await openProduct(page, state, 'regenerate');
        const before = fixture('snapshot', 'regenerate');
        await openMore(page);
        await moreItem(page, 'Tạo lại').click();
        await dialogSubmit(page, /Tạo lại|Xác nhận/);
        let after = fixture('snapshot', 'regenerate');
        for (let i = 0; i < 90 && after.latest_item_id === before.latest_item_id; i++) {
            await page.waitForTimeout(1_000);
            after = fixture('snapshot', 'regenerate');
        }
        expect(after.draft_count).toBeGreaterThanOrEqual(before.draft_count);
        expect(after.latest_item_id).not.toBe(before.latest_item_id);
        for (let i = 0; i < 180 && ! ['needs_review', 'failed', 'blocked'].includes(after.latest_item_status); i++) {
            await page.waitForTimeout(1_000);
            after = fixture('snapshot', 'regenerate');
        }
        expect(['needs_review', 'failed', 'blocked']).toContain(after.latest_item_status);
        const old = fixture('snapshot', 'regenerate');
        expect(old.approval_status).toBe('REJECTED');
        expect(old.rejected_by).toBe(state.operator.id);
    });

    test('hard fact block cannot be approved or applied and job detail is truthful', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'blocked');
        await expect(page.getByText('Bị chặn').first()).toBeVisible();
        await expect(page.getByRole('button', { name: /Duyệt/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Áp dụng/ })).toHaveCount(0);
        await page.goto(`/admin/ai-product-jobs/${state.products.blocked.job_id}/edit`);
        await expect(page.getByText(/Không thể hoàn tất yêu cầu|Bị chặn/).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /Lưu thay đổi/ })).toHaveCount(0);
    });

    test.skip('legacy non-rollout RBAC presentation matrix is superseded by the enforced single-operator rollout matrix', async ({ page }) => {
        test.setTimeout(120_000);
        await login(page, state.users.generate);
        const panelExpectations: Record<string, RegExp> = {
            no_draft: /Chưa tạo|Chưa có|NOT_GENERATED/i,
            processing: /Đang xử lý|đang tạo nội dung/i,
            failed: /Thất bại/i,
            blocked: /Bị chặn/i,
            applied: /Đã áp dụng/i,
        };
        for (const [key, label] of Object.entries(panelExpectations)) {
            await openProduct(page, state, key);
            await expect(page.getByText(label).first()).toBeVisible();
        }
        await openProduct(page, state, 'no_draft');
        await expect(page.getByRole('button', { name: 'Tạo nội dung AI' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toHaveCount(0);

        await openProduct(page, state, 'processing');
        await expect(page.getByRole('button', { name: 'AI đang tạo nội dung…' })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Tạo nội dung AI' })).toHaveCount(0);

        await openProduct(page, state, 'preview');
        await expect(page.getByText('Đã tạo').first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Tạo nội dung AI' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toBeVisible();
        await openMore(page);
        await expect(moreItem(page, 'Tạo lại')).toBeVisible();
        await expect(moreItem(page, 'Từ chối')).toHaveCount(0);
        await expect(moreItem(page, 'Loại bỏ')).toHaveCount(0);

        await login(page, state.users.approve);
        await openProduct(page, state, 'preview');
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Duyệt' })).toBeVisible();
        await expect(page.getByRole('button', { name: /Tạo nội dung AI|Tạo lại|Từ chối|Loại bỏ/ })).toHaveCount(0);
        await openMore(page);
        await expect(moreItem(page, 'Từ chối')).toBeVisible();
        await expect(moreItem(page, 'Loại bỏ')).toBeVisible();
        await expect(moreItem(page, 'Tạo lại')).toHaveCount(0);

        await login(page, state.users.apply);
        await openProduct(page, state, 'apply_rbac');
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Áp dụng' })).toBeVisible();
        await expect(page.getByRole('button', { name: /Duyệt/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Tạo nội dung AI/ })).toHaveCount(0);

        await login(page, state.users.none);
        await openProduct(page, state, 'preview');
        await expect(page.getByRole('button', { name: /Tạo nội dung AI/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Duyệt/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Từ chối|Loại bỏ|Áp dụng/ })).toHaveCount(0);
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });

    test('rollout blocks a non-operator with otherwise sufficient RBAC while retaining preview access', async ({ page }) => {
        await login(page, state.users.full);
        await openProduct(page, state, 'preview');
        await expect(page.getByRole('button', { name: /Xem bản nháp/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Duyệt|Tạo nội dung AI|Áp dụng/i })).toHaveCount(0);
        await openMore(page);
        await expect(moreItem(page, 'Tạo lại')).toHaveCount(0);
        await expect(moreItem(page, 'Từ chối')).toHaveCount(0);
        await expect(moreItem(page, 'Loại bỏ')).toHaveCount(0);
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });

    test('header action hierarchy is responsive without horizontal overflow', async ({ page }) => {
        await login(page, state.operator);
        await openProduct(page, state, 'warning_responsive');
        await expect(page.getByRole('button', { name: 'Xem bản nháp' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Duyệt kèm cảnh báo' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'More' }).last()).toBeVisible();
        await expect(page.getByRole('button', { name: /Tạo lại|Từ chối|Loại bỏ/ })).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await expect(page.getByRole('button', { name: 'Duyệt kèm cảnh báo' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'More' }).last()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        await openMore(page);
        await expect(moreItem(page, 'Tạo lại')).toBeVisible();
        await expect(moreItem(page, 'Từ chối')).toBeVisible();
        await expect(moreItem(page, 'Loại bỏ')).toBeVisible();
    });
});
