import { expect, test } from '@playwright/test';

let runtimeErrors: string[] = [];
test.beforeEach(async ({ page }) => {
    runtimeErrors = [];
    page.on('console', m => { if (m.type() === 'error') runtimeErrors.push(`console:${m.text()}`); });
    page.on('pageerror', e => runtimeErrors.push(`pageerror:${e.message}`));
    page.on('requestfailed', r => runtimeErrors.push(`requestfailed:${r.url()}`));
    page.on('response', r => { if (r.status() >= 500) runtimeErrors.push(`http:${r.status()}:${r.url()}`); });
});
test.afterEach(() => expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]));

const visible = async (page: any, url: string, ids: number[]) => { await page.goto(url); for (const id of ids) await expect(page.locator(`#product-card-${id}`)).toBeVisible(); };
const hidden = async (page: any, url: string, ids: number[]) => { await page.goto(url); for (const id of ids) await expect(page.locator(`#product-card-${id}`)).toHaveCount(0); };

test('01 product listing loads', async ({ page }) => { await page.goto('/san-pham'); await expect(page.locator('[id^=product-card-]').first()).toBeVisible(); });
test('02 Brand filter', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand', [9001, 9002, 9003]));
test('03 Category filter', async ({ page }) => visible(page, '/danh-muc/dieu-hoa-treo-tuong?btu[]=18000', [9001]));
test('04 inverter 1', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&inverter=1', [9001, 9003]));
test('05 inverter 0', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&inverter=0', [9002]));
test('06 invalid inverter', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&inverter=abc', [9001, 9002, 9003]));
test('07 verified BTU', async ({ page }) => visible(page, '/san-pham?btu[]=18000', [9001]));
test('08 multi BTU', async ({ page }) => visible(page, '/san-pham?btu[]=18000&btu[]=48000', [9001, 9003]));
test('09 effective price', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&price_max=16000000', [9001, 9002]));
test('10 active promotion', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&is_sale=1', [9001, 9002]));
for (const [n, sort] of [['11','price_asc'],['12','price_desc'],['13','btu_asc'],['14','btu_desc'],['15','latest']]) test(`${n} ${sort}`, async ({ page }) => { await page.goto(`/san-pham?sort=${sort}`); await expect(page.locator('[id^=product-card-]').first()).toBeVisible(); });
test('16 pagination has no overlap', async ({ page }) => { await page.goto('/san-pham?brand[]=filter-other-brand&sort=price_asc'); const p1=await page.locator('[id^=product-card-]').evaluateAll(es=>es.map(e=>e.id)); await page.goto('/san-pham?brand[]=filter-other-brand&sort=price_asc&page=2'); const p2=await page.locator('[id^=product-card-]').evaluateAll(es=>es.map(e=>e.id)); expect(p1.filter(id=>p2.includes(id))).toEqual([]); });
test('17 Brand page preserves sort', async ({ page }) => { await page.goto('/thuong-hieu/filter-other-brand?sort=price_asc'); await page.getByRole('link',{name:'Go to page 2', exact:true}).click(); await expect(page).toHaveURL(/sort=price_asc/); });
test('18 clear one filter', async ({ page }) => { await page.goto('/san-pham?brand[]=filter-test-brand&btu[]=18000'); await page.getByRole('link',{name:/18.000 BTU/}).click(); await expect(page).not.toHaveURL(/btu/); await expect(page).toHaveURL(/brand/); });
test('19 clear all', async ({ page }) => { await page.goto('/san-pham?brand[]=filter-test-brand'); await page.getByRole('link',{name:'Xóa tất cả'}).first().click(); await expect(page).toHaveURL(/\/san-pham$/); });
test('20 active chips', async ({ page }) => { await page.goto('/san-pham?brand[]=filter-test-brand&btu[]=18000'); await expect(page.getByLabel('Bộ lọc đang áp dụng')).toBeVisible(); });
test('21 zero result', async ({ page }) => { await page.goto('/san-pham?brand[]=missing'); await expect(page.getByText('Không tìm thấy sản phẩm phù hợp.')).toBeVisible(); });
test('22 invalid sort', async ({ page }) => { await page.goto('/san-pham?sort=invalid'); await expect(page.locator('[id^=product-card-]').first()).toBeVisible(); });
test('23 invalid Brand', async ({ page }) => { await page.goto('/san-pham?brand[]=missing'); await expect(page.getByText('Không tìm thấy sản phẩm phù hợp.')).toBeVisible(); });
test('24 invalid BTU', async ({ page }) => visible(page, '/san-pham?brand[]=filter-test-brand&btu[]=invalid', [9001,9002,9003]));
test('25 mobile drawer', async ({ page }) => { await page.setViewportSize({width:390,height:844}); await page.goto('/san-pham'); await page.getByRole('button',{name:'Lọc'}).click(); await expect(page.getByRole('dialog',{name:'Bộ lọc sản phẩm'})).toBeVisible(); await page.keyboard.press('Escape'); await expect(page.getByRole('dialog',{name:'Bộ lọc sản phẩm'})).toBeHidden(); });
test('26 VRF exclusion', async ({ page }) => hidden(page, '/san-pham?btu[]=18000', [9004]));
test('27 unresolved exclusion', async ({ page }) => hidden(page, '/san-pham?btu[]=18000', [9005]));
test('28 unverified exclusion', async ({ page }) => hidden(page, '/san-pham?btu[]=18000', [9006]));
test('29 exact SKU survives more than 200 broad candidates', async ({ page }) => {
    await page.goto('/tim-kiem?q=BROWSER-EXACT-NEEDLE');
    await expect(page.locator('#product-card-9405')).toBeVisible();
    await expect(page.locator('[id^=product-card-]').first()).toHaveAttribute('id', 'product-card-9405');
});
