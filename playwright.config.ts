import { defineConfig } from '@playwright/test';

// Les tests de navigateur réinitialisent la base : ils utilisent une base locale dédiée, jamais celle de .env
// (qui peut pointer vers un serveur distant). Adapter via E2E_DB_USERNAME / E2E_DB_PASSWORD si votre MySQL local en a besoin.
Object.assign(process.env, {
    APP_ENV: 'local',
    DB_CONNECTION: 'mysql',
    DB_HOST: '127.0.0.1',
    DB_PORT: '3306',
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'house_of_challenge_e2e',
    DB_USERNAME: process.env.E2E_DB_USERNAME ?? 'root',
    DB_PASSWORD: process.env.E2E_DB_PASSWORD ?? '',
    PAYMENT_PROVIDER: 'mock',
    PAYMENT_ENV: 'sandbox',
    PAYMENT_SECRET_KEY: '',
    PAYMENT_WEBHOOK_SECRET: 'e2e-webhook-secret',
    MAIL_MAILER: 'log',
    SEED_DEMO_DATA: 'false', // base propre : pas de vendeurs ni de réservations de démonstration
    ADMIN_EMAIL: 'admin@ocdlive.test',
    ADMIN_PASSWORD: 'Admin#12345',
});

export default defineConfig({
    testDir: './tests/e2e',
    workers: 1,
    timeout: 60_000,
    // Le serveur de développement (Vite) compile chaque page à sa première visite : délai d'attente réaliste.
    expect: { timeout: 15_000 },
    use: { baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR', timezoneId: 'Africa/Lome' },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123/up',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
