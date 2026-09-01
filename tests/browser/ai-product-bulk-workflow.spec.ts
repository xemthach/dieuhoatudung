import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';

const root = process.cwd();
type State = {
    operator: { id: number; email: string; password: string };
    users: Record<string, { id: number; email: string; password: string }>;
    products: Record<string, { product_id: number; name: string; model_code: string }>;
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
    await page.goto('/admin/login');
    await page.locator('#form\\.email').fill(actor.email);
    await page.locator('#form\\.password').fill(actor.password);
    await page.getByRole('button', { name: /đăng nhập|login/i }).click();
    await expect(page).toHaveURL(/\/admin(?:\/)?$/);
};

const selectSearchedProduct = async (page: Page, product: State['products'][string]) => {
    const search = page.locator('input[placeholder*="Tìm kiếm"]').last();
    await search.fill(product.model_code);
    await page.waitForTimeout(600);
    const row = page.locator('tr').filter({ hasText: product.model_code }).first();
    await expect(row).toBeVisible();
    await row.locator('input[type="checkbox"]').check();
    await expect(page.getByText(/Đã chọn 1 bản ghi/)).toBeVisible();
};

const openBulkAction = async (page: Page, action: RegExp) => {
    await page.getByRole('button', { name: /Tác vụ hàng loạt/i }).click();
    await page.getByText('AI Product System', { exact: true }).click();
    const panel = page.locator('.fi-dropdown-panel:visible').last();
    await panel.getByText(action).click();
};

const selectAllMatrixProducts = async (page: Page) => {
    const search = page.locator('input[placeholder*="Tìm kiếm"]').last();
    await search.fill('[AI MATRIX]');
    await page.waitForTimeout(800);
    const headerCheckbox = page.locator('thead input[type="checkbox"]').first();
    await headerCheckbox.check();
    const selectAll = page.getByText(/Chọn tất cả \d+/i);
    if (await selectAll.count()) await selectAll.first().click();
    await expect(page.getByText(/Đã chọn \d+ bản ghi/i)).toBeVisible();
};

test.describe.serial('Product List governed AI bulk actions', () => {
    test.use({ viewport: { width: 1440, height: 1000 } });
    let state: State;

    test.beforeAll(() => { state = fixture('setup') as State; });
    test.afterAll(() => { fixture('cleanup'); });
    test.beforeEach(({ page }) => watchRuntime(page));

    test('preflight uses only the checked filtered Product and rollout policy disables mutation for other actors', async ({ page }) => {
        await login(page, state.users.full);
        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.preview);

        await openBulkAction(page, /Duyệt nội dung AI/i);
        const preflight = page.getByTestId('ai-bulk-preflight');
        await expect(preflight).toBeVisible();
        await expect(preflight).toContainText(state.products.preview.name);
        await page.getByRole('button', { name: 'Đóng', exact: true }).last().click();

        await page.getByRole('button', { name: /Tác vụ hàng loạt/i }).click();
        await page.getByText('AI Product System', { exact: true }).click();
        const panel = page.locator('.fi-dropdown-panel:visible').last();
        await expect(panel.getByText(/Duyệt các bản nháp/i)).toBeDisabled();
        await expect(panel.getByText(/Áp dụng nội dung đã duyệt/i)).toBeDisabled();
        expect(fixture('snapshot', 'preview').approval_status).toBe('REVIEW_REQUIRED');

        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });

    test('configured operator can run governed approve, warning approval, reject, discard, mixed apply and regenerate', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, state.operator);

        // Mixed apply: the modal starts with only safe approved drafts checked;
        // selecting the full snapshot proves per-item SUCCESS/SKIPPED/BLOCKED results.
        await page.goto('/admin/products');
        await selectAllMatrixProducts(page);
        await openBulkAction(page, /Áp dụng nội dung đã duyệt/i);
        const applyModal = page.locator('.fi-modal-window:visible').last();
        const preflight = applyModal.getByTestId('ai-bulk-preflight');
        await expect(preflight).toContainText('APPROVED');
        const hardBlockedRow = preflight.locator(`tr[data-product-id="${state.products.blocked.product_id}"]`);
        await expect(hardBlockedRow).toHaveAttribute('data-ai-state', 'APPROVED');
        await expect(hardBlockedRow.locator('td').nth(5)).toHaveText('1');
        const checkboxes = applyModal.locator('input[type="checkbox"]');
        await checkboxes.evaluateAll(inputs => inputs.forEach(input => {
            if (!(input as HTMLInputElement).checked) (input as HTMLElement).click();
        }));
        await applyModal.getByLabel(/Mã xác nhận áp dụng/i).fill('APPLY 2 PRODUCTS');
        await applyModal.getByRole('button', { name: /Xác nhận áp dụng/i }).click();
        await expect.poll(() => fixture('snapshot', 'apply').approval_status).toBe('APPLIED');
        await expect.poll(() => fixture('snapshot', 'apply_rbac').approval_status).toBe('APPLIED');
        await expect(fixture('snapshot', 'stale').approval_status).toBe('APPROVED_FOR_APPLY');
        await expect(fixture('snapshot', 'blocked').approval_status).toBe('APPROVED_FOR_APPLY');

        // Clean approval leaves Product unchanged and records the configured operator.
        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.preview);
        await openBulkAction(page, /Duyệt các bản nháp/i);
        await page.getByRole('button', { name: /Duyệt các bản nháp đủ điều kiện/i }).click();
        await expect.poll(() => fixture('snapshot', 'preview').approval_status).toBe('APPROVED_FOR_APPLY');
        expect(fixture('snapshot', 'preview').approved_by).toBe(state.operator.id);
        expect(fixture('snapshot', 'preview').content_hash).toBe(state.products.preview.content_hash_before);

        // Soft warning cannot become an approval unless the explicit override checkbox is set.
        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.warning);
        await openBulkAction(page, /Duyệt các bản nháp/i);
        const warningModal = page.locator('.fi-modal-window:visible').last();
        await warningModal.getByLabel(/Tôi đã xem cảnh báo chất lượng/i).check();
        await warningModal.getByRole('button', { name: /Duyệt các bản nháp đủ điều kiện/i }).click();
        await expect.poll(() => fixture('snapshot', 'warning').approval_status).toBe('APPROVED_FOR_APPLY');
        expect(fixture('snapshot', 'warning').warning_override).toBe(true);

        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.reject);
        await openBulkAction(page, /Từ chối bản nháp/i);
        const rejectModal = page.locator('.fi-modal-window:visible').last();
        await rejectModal.getByLabel(/Lý do từ chối/i).fill('Operator browser bulk rejection');
        await rejectModal.getByRole('button', { name: /Từ chối các bản nháp/i }).click();
        await expect.poll(() => fixture('snapshot', 'reject').approval_status).toBe('REJECTED');
        expect(fixture('snapshot', 'reject').rejected_by).toBe(state.operator.id);

        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.discard);
        await openBulkAction(page, /Loại bỏ bản nháp/i);
        const discardModal = page.locator('.fi-modal-window:visible').last();
        await discardModal.getByLabel(/Lý do loại bỏ/i).fill('Operator browser logical discard');
        await discardModal.getByRole('button', { name: 'Xác nhận', exact: true }).click();
        await expect.poll(() => fixture('snapshot', 'discard').approval_status).toBe('DISCARDED');
        expect(fixture('snapshot', 'discard').discarded_by).toBe(state.operator.id);

        // One controlled real-provider call on a disposable fixture.
        const regenerateBefore = fixture('snapshot', 'regenerate');
        await page.goto('/admin/products');
        await selectSearchedProduct(page, state.products.regenerate);
        await openBulkAction(page, /Tạo lại nội dung AI/i);
        const regenerateModal = page.locator('.fi-modal-window:visible').last();
        await regenerateModal.getByRole('button', { name: /Gửi các yêu cầu đủ điều kiện/i }).click();
        await expect.poll(() => fixture('snapshot', 'regenerate').latest_item_id, { timeout: 90_000 }).not.toBe(regenerateBefore.latest_item_id);
        await expect.poll(
            () => fixture('snapshot', 'regenerate').latest_item_status,
            { timeout: 180_000 },
        ).toMatch(/^(needs_review|failed|blocked)$/);

        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
