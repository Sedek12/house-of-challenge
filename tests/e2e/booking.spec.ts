import { Browser, expect, Page, test } from '@playwright/test';
import { execSync } from 'node:child_process';

const artisan = (...args: string[]) => execSync(`php artisan hoc:e2e ${args.join(' ')}`, { stdio: 'pipe' }).toString();
const query = (what: string) => artisan('query', what).trim();

const AYA = 'aya@houseofchallenge.test';
const KOFI = 'kofi@houseofchallenge.test';

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/espace/);
}

/** Parcourt le tunnel jusqu'à l'étape créneau (premier créneau libre sélectionné). */
async function pickFirstSlot(page: Page, project: string, mode: string) {
    await page.goto('/reserver');
    await page.getByRole('button', { name: project }).click();
    await page.getByRole('button', { name: mode }).click();
    await page.locator('div.grid button.font-semibold').first().click();
}

const confirmBtn = (page: Page) => page.getByRole('button', { name: /Confirmer ma réservation|Continuer vers le paiement/ });

test.beforeEach(() => artisan('reset'));

test('réservation avec un crédit existant : confirmée immédiatement', async ({ page }) => {
    artisan('grant', AYA);
    await login(page, AYA);
    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await expect(page.getByText('Réservation confirmée')).toBeVisible();
});

test('réservation sans crédit : paiement mock réussi puis confirmation, il reste 3 crédits', async ({ page }) => {
    await login(page, AYA);
    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await expect(page.getByText('Choisissez une formule')).toBeVisible();
    await page.locator('text=Pack 4 passages').locator('xpath=ancestor::div[contains(@class,"rounded-lg")][1]').getByRole('button', { name: 'Payer et confirmer' }).click();
    await page.getByRole('button', { name: 'Simuler succès' }).click();
    await expect(page.getByText('Réservation confirmée')).toBeVisible({ timeout: 20_000 });
    await page.goto('/espace');
    await expect(page.getByText('3', { exact: true }).first()).toBeVisible();
});

test('paiement échoué : aucun crédit', async ({ page }) => {
    await login(page, AYA);
    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await page.getByRole('button', { name: 'Payer et confirmer' }).first().click();
    await page.getByRole('button', { name: 'Simuler échec' }).click();
    await expect(page.getByText('Le paiement n’a pas abouti')).toBeVisible();
});

async function newUserPage(browser: Browser, email: string) {
    const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR' });
    const page = await ctx.newPage();
    await login(page, email);
    return page;
}

test('deux navigateurs sur le même créneau : un seul réussit', async ({ browser }) => {
    artisan('grant', AYA);
    artisan('grant', KOFI);
    const a = await newUserPage(browser, AYA);
    const k = await newUserPage(browser, KOFI);
    await pickFirstSlot(a, 'Projet de Aya', 'Sur place (Togo)');
    await pickFirstSlot(k, 'Projet de Kofi', 'Sur place (Togo)');
    // Chaque page aboutit soit à la confirmation, soit au message « créneau déjà pris ».
    const outcome = (p: Page) =>
        Promise.race([
            p.waitForURL(/\/confirmation\//).then(() => 'ok'),
            p.getByText(/vient d.être réservé/).waitFor().then(() => 'pris'),
        ]);
    const [ra, rk] = await Promise.all([outcome(a), outcome(k), confirmBtn(a).click(), confirmBtn(k).click()]);
    expect([ra, rk].sort()).toEqual(['ok', 'pris']);
});

test('hold expiré : le créneau redevient disponible', async ({ page }) => {
    await login(page, AYA);
    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await expect(page.getByText('Choisissez une formule')).toBeVisible();
    expect(query('held-slots')).toBe('1');
    artisan('expire-holds');
    execSync('php artisan hoc:maintenance', { stdio: 'pipe' });
    expect(query('held-slots')).toBe('0');
    expect(query('first-booking-status')).toBe('ANNULEE');
});

test('annulation > 48 h : crédit rendu ; < 48 h : bouton désactivé', async ({ page }) => {
    artisan('grant', AYA);
    await login(page, AYA);
    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await expect(page.getByText('Réservation confirmée')).toBeVisible();

    await page.goto('/espace/reservations');
    page.once('dialog', (d) => d.accept());
    await page.getByRole('button', { name: 'Annuler' }).click();
    await expect(page.getByText('Réservation annulée.')).toBeVisible();
    await page.goto('/espace');
    await expect(page.getByText('1', { exact: true }).first()).toBeVisible();

    await pickFirstSlot(page, 'Projet de Aya', 'À distance (TikTok)');
    await confirmBtn(page).click();
    await expect(page.getByText('Réservation confirmée')).toBeVisible();
    artisan('near-booking', query('confirmed-booking-id'));
    await page.goto('/espace/reservations');
    await expect(page.getByRole('button', { name: 'Annuler' })).toBeDisabled();
    await expect(page.getByText(/Annulation et report impossibles/)).toBeVisible();
});
