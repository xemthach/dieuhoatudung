import { expect, test } from '@playwright/test';

test('Product detail renders decimal price, no-price fallback, and BTU range without runtime errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(`console:${message.text()}`); });
    page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
    page.on('requestfailed', request => errors.push(`requestfailed:${request.url()}`));
    page.on('response', response => { if (response.status() >= 500) errors.push(`http:${response.status()}:${response.url()}`); });

    const rangeResponse = await page.goto('/san-pham/dieu-hoa-tu-dung-gree-i-shine-inverter-2-chieu-24000-btu-gvh24akxf-k6dnc8a');
    expect(rangeResponse?.status()).toBe(200);
    await page.getByRole('button', { name: 'Thông số kỹ thuật', exact: true }).click();
    await expect(page.getByText('24,225.2 / 28,660.8 BTU', { exact: true })).toBeVisible();

    const numericResponse = await page.goto('/san-pham/dieu-hoa-am-tran-gree-cassette-all-match-inverter-1-chieu-36000-btu-gcc36s6igmc36s6i');
    expect(numericResponse?.status()).toBe(200);
    await expect(page.getByText('39,390,000₫', { exact: true })).toBeVisible();

    const noPriceResponse = await page.goto('/san-pham/dieu-hoa-am-tran-gree-cassette-u-match-inverter-1-chieu-18000-btu-guld50t1a-sguld50w1nha-s');
    expect(noPriceResponse?.status()).toBe(200);
    await expect(page.getByText('Liên hệ để nhận báo giá', { exact: true })).toBeVisible();

    expect(errors, errors.join('\n')).toEqual([]);
});
