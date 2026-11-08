import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    workers: 1,
    timeout: 60_000,
    use: { baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR', timezoneId: 'Africa/Lome' },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123/up',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
