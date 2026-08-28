import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL: process.env.BROWSER_BASE_URL ?? 'http://127.0.0.1:8098',
        browserName: 'chromium',
        channel: 'chrome',
        headless: true,
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    reporter: [['list'], ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }]],
    outputDir: 'storage/framework/testing/playwright-results',
});
