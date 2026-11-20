import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/product-technical-edit-fixture.php', mode], { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Product technical edit and BTU hydration', () => {
    let fixture: any;
    const runtimeErrors: string[] = [];

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('hydrates technical BTU, saves an audited override, and preserves source evidence', async ({ page }) => {
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
        await page.getByText('Thông số kỹ thuật', { exact: true }).click();

        const btu = page.locator('#form\\.technical_capacity_btu');
        const kw = page.locator('#form\\.capacity_kw');
        const hp = page.locator('#form\\.hp');
        const phase = page.locator('#form\\.technical_phase');
        const frequency = page.locator('#form\\.technical_frequency');
        await expect(btu).toBeEditable();
        await expect(kw).toBeEditable();
        await expect(hp).toBeEditable();
        await expect(phase).toBeEditable();
        await expect(frequency).toBeEditable();
        await expect(btu).toHaveValue('12300');
        await expect(kw).toHaveValue('3.6');
        await expect(hp).toHaveValue('1.5');
        await expect(phase).toHaveValue('1');
        await expect(frequency).toHaveValue('50 / 60Hz');

        await btu.fill('12400');
        await kw.fill('3.7');
        await hp.fill('1.6');
        await phase.fill('3');
        await frequency.fill('50Hz');
        await page.locator('#form\\.voltage').fill('220V');
        await page.locator('#form\\.technical_specs_override_reason').fill('Browser-certified installation correction');
        // Let Livewire consolidate all input updates before the audited save.
        await page.waitForTimeout(750);
        await page.getByRole('button', { name: /Lưu thay đổi/i }).click();
        await expect.poll(() => fixtureCommand('snapshot').technical_capacity_btu, {timeout: 20_000}).toBe(12400);

        const snapshot = fixtureCommand('snapshot');
        expect(snapshot.technical_capacity_btu).toBe(12400);
        expect(snapshot.capacity_kw).toBe('3.70');
        expect(snapshot.hp).toBe('1.6');
        expect(snapshot.voltage).toBe('220V');
        expect(snapshot.phase).toBe('3');
        expect(snapshot.frequency).toBe('50Hz');
        expect(snapshot.source).toBe('manual_override');
        expect(snapshot.reason).toBe('Browser-certified installation correction');
        expect(snapshot.overridden_at).toBeTruthy();
        expect(snapshot.spec_capacity_kw).toBe('3.6');

        await page.reload();
        await page.getByText('Thông số kỹ thuật', { exact: true }).click();
        await expect(btu).toHaveValue('12400');
        await expect(kw).toHaveValue('3.7');
        await expect(hp).toHaveValue('1.6');
        await expect(phase).toHaveValue('3');
        await expect(frequency).toHaveValue('50Hz');
        await page.goto('/san-pham/' + fixture.slug);
        const composition = page.getByRole('heading', { name: 'Cấu hình bộ máy', exact: true }).locator('..');
        await expect(composition).toBeVisible();
        await expect(composition.getByText('BRC1H63W', { exact: true })).toBeVisible();
        await expect(composition.getByText('BYCQ125EAF8', { exact: true })).toBeVisible();
        await page.goto('/san-pham/' + fixture.three_phase_slug);
        await expect(page.getByRole('heading', { name: 'Cấu hình bộ máy', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Thông số kỹ thuật', exact: true }).click();
        await expect(page.getByText('380-415V', { exact: true })).toBeVisible();
        await expect(page.getByText('3', { exact: true })).toBeVisible();
        await page.goto('/san-pham/' + fixture.wall_slug);
        await expect(page.getByRole('heading', { name: 'Cấu hình bộ máy', exact: true })).toHaveCount(0);
        await expect(page.getByText('ARC486A33', { exact: true })).toHaveCount(0);
        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
    test('persists a manual extension and finds the rated Daikin by its displayed specifications', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
        page.on('pageerror', e => errors.push(e.message));
        page.on('requestfailed', r => errors.push(r.url()));
        page.on('response', r => { if (r.status() >= 500 || (r.url().includes('livewire') && r.status() >= 400)) errors.push(String(r.status())); });
        await page.goto('/admin/login');
        await page.locator('[id="form.email"]').fill(fixture.email);
        await page.locator('[id="form.password"]').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin\/?$/);
        await page.goto('/admin/products/' + fixture.wall_product_id + '/edit');
        await page.getByText('Thông số kỹ thuật', { exact: true }).click();
        await page.locator('[id="form.technical_capacity_btu"]').fill('11900');
        await page.locator('[id="form.capacity_kw"]').fill('3.5');
        await page.locator('[id="form.hp"]').fill('1.5');
        await page.locator('[id="form.technical_specs_override_reason"]').fill('Kiểm tra công suất trên DB cô lập');
        await page.getByRole('button', { name: 'Thêm thông số mở rộng' }).click();
        const name = page.locator('input[id*="manual_extended_specs"][id$=".key"]');
        const value = page.locator('input[id*="manual_extended_specs"][id$=".value"]');
        await name.fill('tính năng');
        await value.fill('streamer');
        await page.getByRole('button', {name: /Lưu thay đổi/i}).click();
        await expect.poll(() => fixtureCommand('snapshot').wall_feature).toBe('streamer');
        await page.reload();
        await page.getByText('Thông số kỹ thuật', {exact:true}).click();
        await expect(value).toHaveValue('streamer');
        await page.goto('/san-pham');
        const form = page.locator('aside form');
        await form.locator('[name="brand[]"][value=daikin]').check();
        await form.locator('[name="btu[]"][value="9000-12000"]').check();
        await form.locator('[name=inverter][value="1"]').check();
        await form.locator('[name=cooling_type][value="1_chieu"]').check();
        await form.getByRole('button', {name: 'Áp dụng bộ lọc'}).click();
        const card = page.locator('#product-card-' + fixture.wall_product_id);
        for (const text of ['11.900 BTU', '3,5 kW', '1.5 HP']) await expect(card.getByText(text, {exact:true})).toBeVisible();
        await card.getByRole('link').first().click();
        await page.getByRole('button', {name:'Thông số kỹ thuật', exact:true}).click();
        await expect(page.getByRole('row', {name:/Tính năng streamer/i})).toBeVisible();
        for (const text of ['11.900 BTU', '3,5 kW', '1.5 HP']) await expect(page.locator('table:visible').getByText(text, {exact:true}).first()).toBeVisible();
        expect(errors).toEqual([]);
    });
});
