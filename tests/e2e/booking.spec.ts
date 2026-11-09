import { Browser, expect, Page, test } from '@playwright/test';
import { execSync } from 'node:child_process';

const artisan = (...args: string[]) => execSync(`php artisan hoc:e2e ${args.join(' ')}`, { stdio: 'pipe' }).toString();
const query = (what: string) => artisan('query', what).trim();

// PNG 1×1 : suffisant pour tester l'envoi d'une photo produit.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

const freeSlot = (page: Page) => page.locator('button[aria-pressed]:not([disabled])').first();

/**
 * Choisit le premier créneau libre d'un jour assez lointain (4e jour proposé, plus de 48 h) : les tests ne dépendent pas
 * de l'heure à laquelle ils sont lancés (les produits ne sont modifiables que jusqu'à 24 h avant le live).
 */
async function pickFreeSlot(page: Page) {
    await page.getByRole('tab').nth(3).click();
    await freeSlot(page).click();
}

/** Étape 1 : le package. Un clic le choisit et fait passer au créneau ; les autres packages disparaissent. */
async function pickPack(page: Page, name = 'Package Découverte') {
    await page.goto('/reserver');
    await page.getByRole('button', { name: `Choisir ${name}` }).click();
}

/** Étape 2 : premier créneau libre, mode à distance. */
async function pickSlot(page: Page) {
    await pickPack(page);
    await pickFreeSlot(page);
    await page.getByRole('button', { name: /À distance/ }).click();
    await page.getByRole('button', { name: 'Continuer' }).click();
}

/** Étape 3 (vendeur sans compte) : coordonnées, boutique, activité, un produit. */
async function fillForm(page: Page, email = 'aya@example.com', password?: string) {
    await page.getByLabel('Nom complet').fill('Aya Koffi');
    await page.getByLabel('Téléphone WhatsApp').fill('90 12 34 56');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Nom de la boutique').fill('Aya Boutique');
    await page.getByLabel('Ville de la boutique').fill('Lomé');
    await page.getByLabel(/Domaine d.activité/).selectOption('Mode et vêtements');
    await page.getByLabel(/Que vendez-vous/).fill('Robes et tissus wax faits main');
    await page.getByLabel('Nom du produit').fill('Robe wax');
    await page.getByLabel('Prix normal (FCFA)').fill('15000');
    await page.getByLabel('Prix live (FCFA)').fill('12000');
    await page.locator('input[type=file]').setInputFiles({ name: 'robe.png', mimeType: 'image/png', buffer: PNG });
    if (password) {
        await page.getByLabel(/Créer mon compte/).check();
        await page.getByLabel(/Mot de passe/).fill(password);
    }
    await page.getByLabel(/J’accepte/).check();
    await page.getByRole('button', { name: 'Continuer' }).click();
}

const pay = (page: Page) => page.getByRole('button', { name: /^Payer/ }).click();

test.beforeEach(() => artisan('reset'));

test('le package choisi reste affiché, les autres disparaissent, « Changer » y ramène', async ({ page }) => {
    await pickPack(page, 'Package Premium');
    await expect(page.getByText('Package choisi :')).toBeVisible();
    await expect(page.getByText('Package Premium').first()).toBeVisible();
    await expect(page.getByRole('button', { name: /Choisir Package Essentiel/ })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Votre créneau' })).toBeVisible();

    await page.getByRole('button', { name: 'Changer' }).click();
    await expect(page.getByRole('button', { name: /Choisir Package Essentiel/ })).toBeVisible();
    await page.getByRole('button', { name: 'Choisir Package Découverte' }).click();
    await expect(page.getByText('Package Découverte').first()).toBeVisible();
});

test('« Choisir ce package » depuis l’accueil passe directement au créneau', async ({ page }) => {
    await page.goto('/reserver?pack=PREMIUM');
    await expect(page.getByText('Package choisi :')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Votre créneau' })).toBeVisible();
});

test('accueil → « Découvrir les packages » → page des packages → « Choisir ce package » → choix du créneau', async ({ page }) => {
    await page.goto('/');
    // L'accueil ne montre qu'un aperçu des packages, avec le bouton d'accès à la page dédiée.
    await expect(page.getByRole('heading', { name: '6 packages pour tous les budgets' })).toBeVisible();
    await page.getByRole('link', { name: 'Découvrir les packages' }).click();

    await expect(page).toHaveURL(/\/packages$/);
    await expect(page.getByRole('heading', { name: 'Nos packages' })).toBeVisible();
    await expect(page.getByText('Package Partenaire')).toBeVisible();

    // Le détail de chaque package est là : on choisit Premium.
    await page.locator('#PREMIUM').getByRole('link', { name: 'Choisir ce package' }).click();
    await expect(page).toHaveURL(/\/reserver\?pack=PREMIUM/);
    await expect(page.getByText('Package choisi :')).toBeVisible();
    await expect(page.getByText('Package Premium').first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Votre créneau' })).toBeVisible();
});

test('réservation complète sans compte : formulaire, paiement mock, confirmation et lien de suivi', async ({ page }) => {
    await pickSlot(page);
    await fillForm(page);
    await pay(page);
    await expect(page.getByText('Paiement simulé')).toBeVisible();
    await page.getByRole('button', { name: 'Simuler succès' }).click();

    await expect(page.getByRole('heading', { name: 'Votre réservation est confirmée' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText(/OCD-[A-Z0-9]{6}/).first()).toBeVisible();
    await expect(page.getByLabel('Nom du produit')).toHaveValue('Robe wax');
    expect(query('first-booking-status')).toBe('CONFIRMEE');

    // Le lien de suivi permet de modifier les produits (plus de 24 h avant le live).
    await page.getByLabel('Nom du produit').fill('Robe wax XL');
    await page.getByRole('button', { name: 'Enregistrer mes modifications' }).click();
    await expect(page.getByText('Vos produits ont été mis à jour.')).toBeVisible();
});

test('paiement échoué : le créneau est libéré', async ({ page }) => {
    await pickSlot(page);
    await fillForm(page);
    await pay(page);
    await page.getByRole('button', { name: 'Simuler échec' }).click();
    await expect(page.getByText('Le paiement n’a pas abouti')).toBeVisible();
    expect(query('held-slots')).toBe('0');
    expect(query('first-booking-status')).toBe('ANNULEE');
});

test('validation côté client : pas de passage à l’étape suivante sans créneau', async ({ page }) => {
    await pickPack(page);
    await page.getByRole('button', { name: 'Continuer' }).click();
    await expect(page.getByRole('alert')).toContainText('Choisissez un créneau');
});

async function newVisitor(browser: Browser) {
    const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR' });

    return { ctx, page: await ctx.newPage() };
}

test('deux vendeurs sur le même créneau : un seul passe', async ({ browser }) => {
    const a = await newVisitor(browser);
    const b = await newVisitor(browser);

    for (const v of [a, b]) {
        await pickSlot(v.page);
    }
    await fillForm(a.page, 'a@example.com');
    await fillForm(b.page, 'b@example.com');

    await pay(a.page); // A bloque le créneau et part payer
    await expect(a.page.getByText('Paiement simulé')).toBeVisible();

    // B arrive trop tard : soit en validant, soit parce que sa page s'est déjà actualisée (toutes les 30 s) et lui a signalé que le créneau est pris.
    await pay(b.page).catch(() => {});
    await expect(b.page.getByText(/Ce créneau vient d.être pris/).first()).toBeVisible();
    expect(query('held-slots')).toBe('1');

    await a.ctx.close();
    await b.ctx.close();
});

test('blocage expiré : le créneau redevient libre après le passage du cron', async ({ page }) => {
    await pickSlot(page);
    await fillForm(page);
    await pay(page);
    await expect(page.getByText('Paiement simulé')).toBeVisible();
    expect(query('held-slots')).toBe('1');

    artisan('expire-holds');
    execSync('php artisan hoc:maintenance', { stdio: 'pipe' });
    expect(query('held-slots')).toBe('0');
    expect(query('first-booking-status')).toBe('ANNULEE');
});

test('vendeur hors du Togo : passage à distance uniquement et indicatif du pays ajouté au numéro', async ({ page }) => {
    await pickPack(page);
    await page.locator('#country').selectOption('FR');
    await pickFreeSlot(page);
    await expect(page.getByRole('button', { name: /Sur place/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /À distance/ })).toBeVisible();
    await page.getByRole('button', { name: 'Continuer' }).click();

    // Le téléphone suit le pays choisi : France → +33.
    await expect(page.getByLabel('Pays et indicatif du téléphone')).toHaveValue('FR');
    await page.getByLabel('Téléphone WhatsApp').fill('06 12 34 56 78');
    await page.getByLabel('Nom complet').fill('Marie Dupont');
    await page.getByLabel('E-mail').fill('marie@example.com');
    await page.getByLabel('Nom de la boutique').fill('Chez Marie');
    await page.getByLabel('Ville de la boutique').fill('Paris');
    await page.getByLabel(/Domaine d.activité/).selectOption('Beauté et cosmétiques');
    await page.getByLabel(/Que vendez-vous/).fill('Soins capillaires naturels');
    await page.getByLabel('Nom du produit').fill('Huile de karité');
    await page.getByLabel('Prix normal (FCFA)').fill('5000');
    await page.getByLabel('Prix live (FCFA)').fill('4000');
    await page.getByLabel(/J’accepte/).check();
    await page.getByRole('button', { name: 'Continuer' }).click();
    await expect(page.getByText('+33612345678')).toBeVisible();
    await pay(page);
    await page.getByRole('button', { name: 'Simuler succès' }).click();
    await expect(page.getByRole('heading', { name: 'Votre réservation est confirmée' })).toBeVisible({ timeout: 20_000 });
});

test('vendeur qui revient : boutique et produits repris, aucune saisie sauf le consentement', async ({ page }) => {
    // Première réservation avec création du compte : la boutique et ses produits sont mémorisés.
    await pickSlot(page);
    await fillForm(page, 'aya@example.com', 'motdepasse1');
    await pay(page);
    await page.getByRole('button', { name: 'Simuler succès' }).click();
    await expect(page.getByRole('heading', { name: 'Votre réservation est confirmée' })).toBeVisible({ timeout: 20_000 });

    await page.goto('/mon-compte');
    await expect(page.getByRole('heading', { name: 'Mes réservations' })).toBeVisible();
    await expect(page.getByText('Aya Boutique').first()).toBeVisible();
    await expect(page.getByText('1 produit(s) mémorisé(s)')).toBeVisible();

    // Déconnexion puis reconnexion.
    await page.getByRole('button', { name: 'Déconnexion' }).click();
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('aya@example.com');
    await page.getByLabel('Mot de passe').fill('motdepasse1');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/mon-compte/);

    // Deuxième réservation : pack, créneau, puis la boutique et les produits sont déjà là.
    await pickSlot(page);
    await expect(page.getByLabel('Nom complet')).toHaveCount(0); // le nom vient du compte, il n'est pas redemandé
    await expect(page.getByText('Réservation au nom de')).toContainText('Aya Koffi');
    await expect(page.getByText('Robes et tissus wax faits main')).toBeVisible(); // la boutique enregistrée est reprise
    await expect(page.getByText('Modifier cette boutique')).toBeVisible();
    await expect(page.getByLabel('Nom du produit')).toHaveValue('Robe wax');
    await page.getByLabel(/J’accepte/).check();
    await page.getByRole('button', { name: 'Continuer' }).click();
    await pay(page);
    await page.getByRole('button', { name: 'Simuler succès' }).click();
    await expect(page.getByRole('heading', { name: 'Votre réservation est confirmée' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel('Nom du produit')).toHaveValue('Robe wax');
});

test('en-tête : onglets À propos, Packages et Nous contacter', async ({ page }) => {
    await page.goto('/');
    const nav = page.getByRole('navigation', { name: 'Navigation principale' });
    await expect(nav.getByRole('link', { name: 'Packs' })).toHaveCount(0); // le mot est « Packages »

    await nav.getByRole('link', { name: 'À propos' }).click();
    await expect(page.getByRole('heading', { name: 'À propos' })).toBeVisible();
    await expect(page.getByText('Les Opportunités, C’est Dehors').first()).toBeVisible();
    await expect(page.getByText('Bovann Group Togo').first()).toBeVisible();

    await nav.getByRole('link', { name: 'Packages' }).click();
    await expect(page.getByRole('heading', { name: 'Nos packages' })).toBeVisible();

    await nav.getByRole('link', { name: 'Nous contacter' }).click();
    await expect(page.getByRole('heading', { name: 'Nous contacter' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Écrire sur WhatsApp' })).toHaveAttribute('href', /wa\.me\/\d+/);
    await expect(page.getByRole('link', { name: '@houseofchallenge' }).first()).toHaveAttribute('href', /tiktok\.com\/@houseofchallenge/);
});

test('téléphone : menu avec les mêmes onglets', async ({ browser }) => {
    const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR', viewport: { width: 360, height: 740 } });
    const page = await ctx.newPage();
    await page.goto('/');
    await expect(page.getByRole('navigation', { name: 'Navigation principale' })).toBeHidden();

    await page.getByRole('button', { name: 'Menu' }).click();
    const menu = page.getByRole('navigation', { name: 'Menu mobile' });
    await expect(menu.getByRole('link', { name: 'À propos' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Packages' })).toBeVisible();
    await menu.getByRole('link', { name: 'Nous contacter' }).click();
    await expect(page.getByRole('heading', { name: 'Nous contacter' })).toBeVisible();
    await ctx.close();
});

test('pied de page : « Développé par Horizon Digital » sur le site public', async ({ page }) => {
    for (const path of ['/', '/packages', '/contact', '/login']) {
        await page.goto(path);
        await expect(page.locator('footer').getByText('Développé par Horizon Digital')).toBeVisible();
    }
    // Pied de page structuré : navigation, contact, informations.
    const footer = page.locator('footer');
    await expect(footer.getByRole('heading', { name: 'Navigation' })).toBeVisible();
    await expect(footer.getByRole('heading', { name: 'Contact' })).toBeVisible();
    await expect(footer.getByRole('heading', { name: 'Informations' })).toBeVisible();
    await expect(footer.getByText(/© \d{4} OCD Live/)).toBeVisible();
});

test('tableau de bord administrateur : barre latérale, en-tête, contenu et mention de l’éditeur', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('admin@ocdlive.test');
    await page.getByLabel('Mot de passe').fill('Admin#12345');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    const sidebar = page.getByRole('navigation', { name: 'Navigation du tableau de bord' });
    for (const label of ['Planning', 'Réservations', 'Créneaux', 'Packages', 'Paiements', 'Paramètres']) {
        await expect(sidebar.getByRole('link', { name: label })).toBeVisible();
    }
    await expect(sidebar.getByRole('link', { name: 'Planning' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('banner').getByRole('heading', { name: 'Planning' })).toBeVisible(); // en-tête
    await expect(page.getByRole('contentinfo').getByText('Développé par Horizon Digital')).toBeVisible();

    await sidebar.getByRole('link', { name: 'Réservations' }).click();
    await expect(page).toHaveURL(/\/admin\/reservations/);
    await expect(sidebar.getByRole('link', { name: 'Réservations' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('banner').getByRole('heading', { name: 'Réservations' })).toBeVisible();

    await page.getByRole('button', { name: 'Déconnexion' }).click();
    await expect(page).toHaveURL(/\/$/);
});

test('tableau de bord sur téléphone : la barre latérale s’ouvre par le bouton du menu', async ({ browser }) => {
    const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR', viewport: { width: 380, height: 760 } });
    const page = await ctx.newPage();
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('admin@ocdlive.test');
    await page.getByLabel('Mot de passe').fill('Admin#12345');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
    const sidebar = page.getByRole('navigation', { name: 'Navigation du tableau de bord' });
    await expect(sidebar.getByRole('link', { name: 'Paiements' })).toBeVisible();
    await sidebar.getByRole('link', { name: 'Paiements' }).click();
    await expect(page).toHaveURL(/\/admin\/paiements/);
    await ctx.close();
});

test('en-tête : un onglet « Accueil » explicite, sur ordinateur et sur téléphone', async ({ page, browser }) => {
    // Ordinateur : « Accueil » est le premier onglet, surligné sur la page d'accueil, et ramène à l'accueil depuis les autres pages.
    await page.goto('/');
    const nav = page.getByRole('navigation', { name: 'Navigation principale' });
    const tabs = await nav.getByRole('link').allTextContents();
    expect(tabs.map((t) => t.trim())).toEqual(['Accueil', 'À propos', 'Packages', 'Nous contacter']);
    await expect(nav.getByRole('link', { name: 'Accueil' })).toHaveAttribute('aria-current', 'page');

    await nav.getByRole('link', { name: 'Packages' }).click();
    await expect(page).toHaveURL(/\/packages$/);
    await expect(nav.getByRole('link', { name: 'Accueil' })).not.toHaveAttribute('aria-current', 'page');
    await nav.getByRole('link', { name: 'Accueil' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { level: 1 }).first()).toContainText('Présentez vos produits');

    // Téléphone : même onglet dans le menu.
    const ctx = await browser.newContext({ baseURL: 'http://127.0.0.1:8123', locale: 'fr-FR', viewport: { width: 360, height: 740 } });
    const phone = await ctx.newPage();
    await phone.goto('/contact');
    await phone.getByRole('button', { name: 'Menu' }).click();
    await phone.getByRole('navigation', { name: 'Menu mobile' }).getByRole('link', { name: 'Accueil' }).click();
    await expect(phone).toHaveURL(/\/$/);
    await ctx.close();
});

test('écran de chargement : logo avec un cercle qui tourne, visible tant que l’application charge, puis retiré', async ({ page, request }) => {
    // 1. Le serveur l'envoie dans le HTML initial : il s'affiche avant tout JavaScript.
    const html = await (await request.get('/')).text();
    expect(html).toContain('id="app-loader"');
    expect(html).toContain('app-loader__ring');
    expect(html).toMatch(/<img class="app-loader__logo" src="[^"]*logo[^"]*"/);

    // 2. On retarde le JavaScript de l'application : l'écran de chargement reste visible et le cercle tourne.
    await page.route(/\/(app\.tsx|assets\/app-[^/]+\.js)/, async (route) => {
        await new Promise((r) => setTimeout(r, 2500));
        await route.continue();
    });
    await page.goto('/', { waitUntil: 'commit' });
    const loader = page.locator('#app-loader');
    await expect(loader).toBeVisible();
    await expect(page.locator('.app-loader__logo')).toBeVisible();
    const anim = await page.locator('.app-loader__ring').evaluate((el) => {
        const s = getComputedStyle(el);
        return { name: s.animationName, state: s.animationPlayState, iterations: s.animationIterationCount };
    });
    expect(anim).toEqual({ name: 'app-loader-spin', state: 'running', iterations: 'infinite' });
    const angle1 = await page.locator('.app-loader__ring').evaluate((el) => getComputedStyle(el).transform);
    await page.waitForTimeout(250);
    const angle2 = await page.locator('.app-loader__ring').evaluate((el) => getComputedStyle(el).transform);
    expect(angle2).not.toBe(angle1); // le cercle a bien tourné entre les deux mesures

    // 3. Une fois l'application affichée, l'écran de chargement disparaît et la page est utilisable.
    await expect(page.getByRole('heading', { level: 1 }).first()).toContainText('Présentez vos produits', { timeout: 20_000 });
    await expect(loader).toBeHidden({ timeout: 10_000 });
    await page.getByRole('link', { name: 'Découvrir les packages' }).click();
    await expect(page).toHaveURL(/\/packages$/);
});
