import { resolve } from 'node:path';
import { defineConfig } from '@playwright/test';

const browserDatabase = resolve('storage/framework/testing/product-data-governance-browser-isolated.sqlite');
const browserEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    BROWSER_ALLOW_REAL_PROVIDER: '0',
    AI_PRODUCT_REAL_PROVIDER_TEST: '0',
    AI_GUARD_REAL_PROVIDER_TEST: '0',
    APP_CONFIG_CACHE: 'storage/framework/testing/browser-config.php',
    APP_ROUTES_CACHE: 'storage/framework/testing/browser-routes.php',
    AI_SINGLE_OPERATOR_ENABLED: 'true',
    AI_SINGLE_OPERATOR_USER_ID: '1',
    AI_SINGLE_OPERATOR_SUPER_ADMIN_EXCEPTION: 'true',
    AI_SINGLE_OPERATOR_ENFORCE_IN_TESTING: 'true',
    APP_DEBUG: 'false',
    APP_URL: 'http://127.0.0.1:8098',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: browserDatabase,
    QUEUE_CONNECTION: 'sync',
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'database',
    MAIL_MAILER: 'array',
};

Object.assign(process.env, browserEnvironment);

export default defineConfig({
    testDir: './tests/browser',
    testIgnore: ['**/*real-provider.spec.ts', '**/ai-live-parity-readonly.spec.ts'],
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
    globalSetup: './tests/browser/global-setup.ts',
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8098 --no-reload',
        url: 'http://127.0.0.1:8098/up',
        timeout: 120_000,
        reuseExistingServer: false,
        env: browserEnvironment,
    },
    reporter: [['list'], ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }]],
    outputDir: 'storage/framework/testing/playwright-results',
});
