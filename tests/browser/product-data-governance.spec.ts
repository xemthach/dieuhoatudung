import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const root = process.cwd();
const fixtureCommand = (mode: 'setup' | 'snapshot' | 'cleanup'): any => JSON.parse(execFileSync(
    'php', ['tests/browser/product-data-governance-fixture.php', mode], { cwd: root, encoding: 'utf8' },
));

test.describe.serial('Product taxonomy and marketing-capacity governance', () => {
    let fixture: any;
    const runtimeErrors: string[] = [];

    test.beforeAll(() => { fixture = fixtureCommand('setup'); });
    test.afterAll(() => { fixtureCommand('cleanup'); });

    test('approves exact ledgers through Admin and filters by explicit rated capacity', async ({ page }) => {
        test.setTimeout(90_000);
        page.on('console', message => { if (message.type() === 'error') runtimeErrors.push(`console:${message.text()}`); });
        page.on('pageerror', error => runtimeErrors.push(`pageerror:${error.message}`));
        page.on('requestfailed', request => runtimeErrors.push(`requestfailed:${request.url()}`));
        page.on('response', response => { if (response.status() >= 500) runtimeErrors.push(`http:${response.status()}:${response.url()}`); });

        await page.goto('/admin/login');
        await page.locator('#form\\.email').fill(fixture.email);
        await page.locator('#form\\.password').fill(fixture.password);
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin(?:\/)?$/);
        await page.goto('/admin/product-data-governance');

        await expect(page.getByRole('heading', { name: 'Product Taxonomy & Marketing Capacity Review' })).toBeVisible();
        await expect(page.getByText('Taxonomy Review', { exact: true })).toBeVisible();
        await expect(page.getByText('Marketing Capacity Review', { exact: true })).toBeVisible();
        await page.locator('input[placeholder="Required approval reason"]').fill('Browser exact taxonomy ledger approval');
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Approve deterministic proposals' }).click();
        await expect.poll(() => fixtureCommand('snapshot').taxonomy_approvals).toBe(1);

        const mapping = page.locator('details[data-marketing-group]').first();
        await mapping.evaluate((element: HTMLDetailsElement) => { element.open = true; });
        await mapping.locator('input[type="number"]').fill('24000');
        await mapping.locator('input[placeholder="Required source-backed business reason"]').fill('Browser source-backed commercial class approval');
        page.once('dialog', dialog => dialog.accept());
        await mapping.getByRole('button', { name: 'Approve mapping' }).click();
        await expect.poll(() => fixtureCommand('snapshot').marketing_approvals).toBe(1);

        await page.goto('/san-pham?btu[]=18000');
        await expect(page.locator(`#product-card-${fixture.verified_id}`)).toBeVisible();
        await expect(page.locator(`#product-card-${fixture.unverified_id}`)).toBeVisible();
        await expect(page.locator(`#product-card-${fixture.unresolved_id}`)).toBeVisible();
        await expect(page.locator(`#product-card-${fixture.vrf_id}`)).toHaveCount(0);
        await expect(page.getByText(/18\.000 BTU/).first()).toBeVisible();

        await page.goto('/san-pham?btu[]=24000');
        await expect(page.locator(`#product-card-${fixture.cassette_id}`)).toBeVisible();
        await page.goto('/danh-muc/dieu-hoa-am-tran-cassette?btu[]=24000');
        await expect(page.locator(`#product-card-${fixture.cassette_id}`)).toBeVisible();

        expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
    });
});
