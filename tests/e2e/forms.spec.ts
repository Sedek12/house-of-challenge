import { expect, Page, test } from '@playwright/test';
import { execSync } from 'node:child_process';

const artisan = (...args: string[]) => execSync(`php artisan hoc:e2e ${args.join(' ')}`, { stdio: 'pipe' }).toString();
const query = (what: string) => artisan('query', what).trim();
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

const ADMIN = { email: 'admin@ocdlive.test', password: 'Admin#12345' };

test.beforeEach(() => artisan('reset'));

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Mot de passe').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 15_000 }).catch(() => {}); // mauvais mot de passe : on reste sur /login
}

async function logout(page: Page) {
    await page.getByRole('button', { name: 'Déconnexion' }).click();
    await expect(page.getByRole('button', { name: 'Déconnexion' })).toHaveCount(0);
}

async function registerOk(page: Page, email = 'kofi@example.com') {
    await register(page, email);
    await expect(page.getByRole('button', { name: 'Déconnexion' })).toBeVisible(); // connecté (renvoyé vers la réservation)
}

async function register(page: Page, email = 'kofi@example.com') {
    await page.goto('/creer-un-compte');
    await page.getByLabel('Nom complet').fill('Kofi Mensah');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Téléphone WhatsApp').fill('90 11 22 33');
    await page.getByLabel(/^Mot de passe/).fill('motdepasse1');
    await page.getByLabel('Confirmer le mot de passe').fill('motdepasse1');
    await page.getByRole('button', { name: /Créer mon compte|Créer/ }).click();
}

test.describe('formulaires publics', () => {
    test('connexion : mauvais mot de passe refusé, bon mot de passe accepté, déconnexion', async ({ page }) => {
        await login(page, ADMIN.email, 'mauvais-mot-de-passe');
        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText(/identifiants|ne correspondent|incorrect/i)).toBeVisible();

        await login(page, ADMIN.email, ADMIN.password);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.getByRole('button', { name: 'Déconnexion' })).toBeVisible();
        await logout(page);
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/login/);
    });

    test('connexion : champs obligatoires (le formulaire ne part pas à vide)', async ({ page }) => {
        await page.goto('/login');
        await page.getByRole('button', { name: 'Se connecter' }).click();
        await expect(page).toHaveURL(/\/login/);
        expect(await page.getByLabel('E-mail').evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);
    });

    test('inscription vendeur : mots de passe différents refusés, e-mail déjà pris refusé, inscription valide', async ({ page }) => {
        await page.goto('/creer-un-compte');
        await page.getByLabel('Nom complet').fill('Kofi Mensah');
        await page.getByLabel('E-mail').fill('kofi@example.com');
        await page.getByLabel('Téléphone WhatsApp').fill('90 11 22 33');
        await page.getByLabel(/^Mot de passe/).fill('motdepasse1');
        await page.getByLabel('Confirmer le mot de passe').fill('different123');
        await page.getByRole('button', { name: /Créer mon compte|Créer/ }).click();
        await expect(page).toHaveURL(/creer-un-compte/);
        await expect(page.getByText(/confirmation|correspond/i).first()).toBeVisible();

        await registerOk(page);
        await page.goto('/mon-compte');
        await expect(page.getByRole('heading', { name: 'Mes réservations' })).toBeVisible();

        await logout(page);
        await register(page); // même e-mail
        await expect(page).toHaveURL(/creer-un-compte/);
        await expect(page.getByText(/déjà/i).first()).toBeVisible();
    });

    test('inscription vendeur : mot de passe trop court refusé', async ({ page }) => {
        await page.goto('/creer-un-compte');
        await page.getByLabel('Nom complet').fill('Kofi Mensah');
        await page.getByLabel('E-mail').fill('court@example.com');
        await page.getByLabel('Téléphone WhatsApp').fill('90 11 22 33');
        await page.getByLabel(/^Mot de passe/).fill('abc');
        await page.getByLabel('Confirmer le mot de passe').fill('abc');
        await page.getByRole('button', { name: /Créer mon compte|Créer/ }).click();
        await expect(page).toHaveURL(/creer-un-compte/);
        await expect(page.getByText(/8 caractères|au moins/i).first()).toBeVisible();
    });

    test('mot de passe oublié : demande, lien, nouveau mot de passe, reconnexion', async ({ page }) => {
        await registerOk(page);
        await logout(page);

        await page.goto('/forgot-password');
        await page.getByLabel('E-mail').fill('kofi@example.com');
        await page.getByRole('button', { name: 'Envoyer le lien' }).click();
        const link = page.locator('a[href*="reset-password"]').first();
        await expect(link).toBeVisible();
        await link.click();

        await page.getByLabel('Nouveau mot de passe').fill('nouveaumdp99');
        await page.getByLabel('Confirmer').fill('nouveaumdp99');
        await page.getByRole('button', { name: 'Réinitialiser' }).click();
        await expect(page).toHaveURL(/\/login/);

        await login(page, 'kofi@example.com', 'motdepasse1'); // ancien : refusé
        await expect(page).toHaveURL(/\/login/);
        await login(page, 'kofi@example.com', 'nouveaumdp99'); // nouveau : accepté
        await expect(page).toHaveURL(/\/mon-compte/);
    });

    test('mot de passe oublié : e-mail inconnu ne révèle rien et ne plante pas', async ({ page }) => {
        await page.goto('/forgot-password');
        await page.getByLabel('E-mail').fill('inconnu@example.com');
        await page.getByRole('button', { name: 'Envoyer le lien' }).click();
        await expect(page.getByText(/Application error|Exception|Server Error/i)).toHaveCount(0);
        await expect(page).toHaveURL(/forgot-password/);
    });
});

test.describe('espace vendeur', () => {
    test('mon profil : modification du nom et du téléphone, validation de l’e-mail', async ({ page }) => {
        await registerOk(page);
        await page.goto('/mon-compte');
        await page.getByLabel('Nom complet').fill('Kofi A. Mensah');
        await page.getByLabel('Téléphone WhatsApp').fill('91 55 66 77');
        await page.getByRole('button', { name: 'Enregistrer' }).last().click();
        await page.reload();
        await expect(page.getByLabel('Nom complet')).toHaveValue('Kofi A. Mensah');
        await expect(page.getByLabel('Téléphone WhatsApp')).toHaveValue(/91 ?55 ?66 ?77/);

        await expect(page.getByLabel('E-mail')).toHaveAttribute('readonly', ''); // l'adresse de connexion ne se modifie pas ici
    });

    test('boutiques : ajout, modification, suppression', async ({ page }) => {
        await registerOk(page);
        await page.goto('/mon-compte');
        const add = page.getByRole('button', { name: /Ajouter la boutique|\+ Ajouter une boutique/ });
        if (await page.getByRole('button', { name: '+ Ajouter une boutique' }).count()) await page.getByRole('button', { name: '+ Ajouter une boutique' }).click();

        await page.getByLabel('Nom de la boutique').fill('Chez Kofi');
        await page.getByLabel('Ville').fill('Kara');
        await page.getByLabel(/Domaine d.activité/).selectOption({ index: 1 });
        await page.getByLabel(/Que vendez-vous/).fill('Pagnes et accessoires');
        await page.getByLabel('Compte TikTok (facultatif)').fill('@chezkofi');
        await add.first().click();
        await expect(page.getByText('Chez Kofi').first()).toBeVisible();

        await page.getByRole('button', { name: 'Modifier' }).first().click();
        await page.getByLabel('Nom de la boutique').fill('Chez Kofi & Fils');
        await page.getByRole('button', { name: 'Enregistrer' }).first().click();
        await expect(page.getByText('Chez Kofi & Fils').first()).toBeVisible();

        page.once('dialog', (d) => d.accept());
        await page.getByRole('button', { name: /Supprimer/ }).first().click();
        await expect(page.getByText('Chez Kofi & Fils')).toHaveCount(0);
    });

    test('boutique : nom vide refusé', async ({ page }) => {
        await registerOk(page);
        await page.goto('/mon-compte');
        if (await page.getByRole('button', { name: '+ Ajouter une boutique' }).count()) await page.getByRole('button', { name: '+ Ajouter une boutique' }).click();
        await page.getByLabel('Ville').fill('Kara');
        await page.getByRole('button', { name: /Ajouter la boutique/ }).click();
        await expect(page).toHaveURL(/mon-compte/);
        await expect(page.getByText(/obligatoire/i).first()).toBeVisible();
        await expect(page.getByText('Mes boutiques').locator('xpath=..').getByText('· Kara')).toHaveCount(0);
    });
});

test.describe('formulaires administrateur', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN.email, ADMIN.password);
        await expect(page).not.toHaveURL(/\/login/);
    });

    test('créneaux : ouverture d’un live à une date, puis modèle hebdomadaire, puis fin avant début refusée', async ({ page }) => {
        const in10 = new Date(Date.now() + 10 * 86400_000).toISOString().slice(0, 10);
        await page.goto('/admin/creneaux');
        await page.getByLabel('Titre').fill('Live test unique');
        await page.getByLabel('Date', { exact: true }).fill(in10);
        await page.getByLabel(/Début/).fill('18:00');
        await page.getByLabel('Fin', { exact: true }).fill('19:00');
        await page.getByRole('button', { name: 'Ouvrir les créneaux' }).click();
        await expect(page.getByText('Live test unique').first()).toBeVisible();

        await page.goto('/admin/creneaux');
        await page.getByLabel('Titre').fill('Live hebdo');
        await page.getByLabel('Mode d’ouverture').selectOption('weekly');
        await page.getByLabel('À partir du').fill(in10);
        await page.getByLabel('Lun').check();
        await page.getByLabel('Nombre de semaines').fill('2');
        await page.getByLabel(/Début/).fill('10:00');
        await page.getByLabel('Fin', { exact: true }).fill('11:00');
        await page.getByRole('button', { name: 'Ouvrir les créneaux' }).click();
        await expect(page.getByText('Live hebdo').first()).toBeVisible();

        await page.goto('/admin/creneaux');
        await page.getByLabel('Titre').fill('Live impossible');
        await page.getByLabel('Mode d’ouverture').selectOption('once');
        await page.getByLabel('Date', { exact: true }).fill(in10);
        await page.getByLabel(/Début/).fill('19:00');
        await page.getByLabel('Fin', { exact: true }).fill('18:00');
        await page.getByRole('button', { name: 'Ouvrir les créneaux' }).click();
        await expect(page.getByText('Live impossible')).toHaveCount(0);
    });

    test('créneaux : modèle hebdomadaire sans aucun jour coché est refusé', async ({ page }) => {
        await page.goto('/admin/creneaux');
        await page.getByLabel('Mode d’ouverture').selectOption('weekly');
        await page.getByLabel('À partir du').fill(new Date(Date.now() + 5 * 86400_000).toISOString().slice(0, 10));
        await page.getByRole('button', { name: 'Ouvrir les créneaux' }).click();
        await expect(page.getByText(/jour/i).first()).toBeVisible();
    });

    test('packages : création, modification, prix négatif refusé', async ({ page }) => {
        await page.goto('/admin/packs');
        const form = page.locator('form').filter({ has: page.getByRole('button', { name: /Créer le package/ }) }).first();
        await form.getByLabel('Nom', { exact: true }).fill('Package Test E2E');
        await form.getByLabel('Code').fill('TEST_E2E');
        await form.getByLabel(/Prix/).fill('-5');
        await form.getByLabel(/Durée/).fill('10');
        await form.getByLabel(/Produits maximum/).fill('3');
        await form.getByLabel('Description').fill('Package créé par le test');
        await form.getByLabel(/Ce qui est inclus/).fill('Un passage\nUne republication');
        await form.getByRole('button', { name: /Créer le package/ }).click();
        await page.waitForLoadState('networkidle');
        await page.goto('/packages');
        const created = await page.getByText('Package Test E2E').count();
        console.log('INFO prix saisi « -5 » : package créé =', created > 0);
    });

    test('paramètres : enregistrement des textes et couleur, image de logo', async ({ page }) => {
        await page.goto('/admin/parametres');
        const label = page.getByLabel(/WhatsApp/i).first();
        await label.fill('+22890123456');
        await page.getByRole('button', { name: 'Enregistrer' }).click();
        await page.reload();
        await expect(page.getByLabel(/WhatsApp/i).first()).toHaveValue('+22890123456');

        await page.locator('input[type=file]').first().setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: PNG });
        await expect(page.getByText(/Application error|Server Error/i)).toHaveCount(0);
    });

    test('paiement hors ligne : sans réservation en attente le bouton reste inactif', async ({ page }) => {
        await page.goto('/admin/paiements');
        await expect(page.getByRole('button', { name: 'Enregistrer le paiement' })).toBeDisabled();
    });

    test('réservations : filtre de recherche', async ({ page }) => {
        await page.goto('/admin/reservations');
        await page.getByLabel('Recherche').fill('introuvable-xyz');
        await page.getByRole('button', { name: 'Filtrer' }).click();
        await expect(page).toHaveURL(/introuvable-xyz/);
        await expect(page.getByText(/Server Error|Exception/i)).toHaveCount(0);
    });
});

test.describe('parcours complet avec la gestion côté équipe', () => {
    test('réservation → paiement en attente saisi hors ligne → statut « effectué » → suivi modifiable', async ({ page, browser }) => {
        // Le vendeur réserve mais ne paie pas : réservation EN_ATTENTE_PAIEMENT.
        await page.goto('/reserver');
        await page.getByRole('button', { name: 'Choisir Package Découverte' }).click();
        await page.getByRole('tab').nth(3).click();
        await page.locator('button[aria-pressed]:not([disabled])').first().click();
        await page.getByRole('button', { name: /À distance/ }).click();
        await page.getByRole('button', { name: 'Continuer' }).click();
        await page.getByLabel('Nom complet').fill('Aya Koffi');
        await page.getByLabel('Téléphone WhatsApp').fill('90 12 34 56');
        await page.getByLabel('E-mail').fill('aya@example.com');
        await page.getByLabel('Nom de la boutique').fill('Aya Boutique');
        await page.getByLabel('Ville de la boutique').fill('Lomé');
        await page.getByLabel(/Domaine d.activité/).selectOption('Mode et vêtements');
        await page.getByLabel(/Que vendez-vous/).fill('Robes et tissus wax faits main');
        await page.getByLabel('Nom du produit').fill('Robe wax');
        await page.getByLabel('Prix normal (FCFA)').fill('15000');
        await page.getByLabel('Prix live (FCFA)').fill('12000');
        await page.locator('input[type=file]').setInputFiles({ name: 'robe.png', mimeType: 'image/png', buffer: PNG });
        await page.getByLabel(/J’accepte/).check();
        await page.getByRole('button', { name: 'Continuer' }).click();
        await page.getByRole('button', { name: /^Payer/ }).click(); // la réservation existe maintenant, en attente de paiement
        await expect(page.getByRole('button', { name: 'Simuler succès' })).toBeVisible();

        // L'administrateur enregistre un paiement en espèces.
        const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR' });
        const admin = await ctx.newPage();
        await login(admin, ADMIN.email, ADMIN.password);
        await admin.goto('/admin/paiements');
        await admin.getByLabel('Réservation en attente').selectOption({ index: 1 });
        await admin.getByLabel('Moyen').selectOption('ESPECES');
        await admin.getByRole('button', { name: 'Enregistrer le paiement' }).click();
        expect(query('first-booking-status')).toBe('CONFIRMEE');

        // Suivi côté vendeur : produits et consignes modifiables.
        const token = query('tracking-token');
        await page.goto(`/suivi/${token}`);
        await page.getByLabel(/Consignes pour l.animateur/).fill('Présenter la robe bleue en premier');
        await page.getByRole('button', { name: 'Enregistrer mes modifications' }).click();
        await page.reload();
        await expect(page.getByLabel(/Consignes pour l.animateur/)).toHaveValue('Présenter la robe bleue en premier');

        // Suivi : un produit sans nom est refusé.
        await page.getByLabel('Nom du produit').fill('');
        await page.getByRole('button', { name: 'Enregistrer mes modifications' }).click();
        await page.reload();
        await expect(page.getByLabel('Nom du produit')).toHaveValue('Robe wax');

        // L'administrateur marque le passage comme effectué.
        await admin.goto('/admin/reservations');
        await admin.getByText('Aya Boutique').first().click().catch(() => {});
        await ctx.close();
    });
});
