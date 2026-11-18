import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const browserDatabase = resolve('storage/framework/testing/product-data-governance-browser-isolated.sqlite');

export default function globalSetup(): void {
    const browserEnvironment = {
        ...process.env,
        APP_ENV: 'testing',
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
}
