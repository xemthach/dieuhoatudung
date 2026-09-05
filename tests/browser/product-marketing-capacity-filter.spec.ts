import { expect, test } from '@playwright/test';

test('current Local marketing-capacity filters return the audited Product IDs', async ({ page }) => {
    const runtimeErrors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') runtimeErrors.push('console:' + message.text()); });
    page.on('pageerror', error => runtimeErrors.push('pageerror:' + error.message));
    page.on('requestfailed', request => runtimeErrors.push('requestfailed:' + request.url()));
    page.on('response', response => { if (response.status() >= 500) runtimeErrors.push('http:' + response.status() + ':' + response.url()); });

    const assertCards = async (url: string, expectedIds: number[]) => {
        await page.goto(url);
        for (const id of expectedIds) {
            await expect(page.locator('#product-card-' + id)).toBeVisible();
        }
    };

    await assertCards('/san-pham?btu[]=18000', [1246, 1258]);
    await assertCards('/san-pham?btu[]=24000', [1237, 1240, 1247, 1259]);
    await assertCards('/san-pham?btu[]=48000', [1250, 1261]);
    await assertCards('/san-pham?btu[]=18000&btu[]=48000', [1246, 1250, 1258, 1261]);
    await assertCards('/san-pham?brand[]=gree&btu[]=18000', [1246, 1258]);
    await assertCards('/danh-muc/dieu-hoa-treo-tuong?btu[]=18000', [1246, 1258]);
    await assertCards('/san-pham?btu[]=18000&inverter=1', [1258]);

    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
});
