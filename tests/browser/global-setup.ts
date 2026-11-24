import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const browserDatabase = resolve('storage/framework/testing/product-data-governance-browser-isolated.sqlite');

export default function globalSetup(): void {
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

    mkdirSync(dirname(browserDatabase), { recursive: true });
    rmSync(resolve(browserEnvironment.APP_CONFIG_CACHE), { force: true });
    rmSync(resolve(browserEnvironment.APP_ROUTES_CACHE), { force: true });
    execFileSync('php', ['artisan', 'migrate:fresh', '--force', '--no-ansi'], {
        cwd: process.cwd(),
        env: browserEnvironment,
        stdio: 'inherit',
    });
    execFileSync('php', ['tests/browser/isolated-database-seeder.php'], {
        cwd: process.cwd(),
        env: browserEnvironment,
        stdio: 'inherit',
    });
    execFileSync('php', ['tests/browser/configure-http-runtime.php'], {
        cwd: process.cwd(), env: browserEnvironment, stdio: 'inherit',
    });
}
