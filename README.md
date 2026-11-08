# House of Challenge — plateforme d'enregistrement (OCD)

Laravel 12 · React 19 (Inertia) · TypeScript · Tailwind 4 · MySQL.

## Installation locale

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# créer la base : CREATE DATABASE house_of_challenge CHARACTER SET utf8mb4;
php artisan migrate --seed        # admin + formules + paramètres + données de démo
php artisan storage:link
composer dev                      # ou : php artisan serve  +  npm run dev
```

Comptes de démo (mot de passe `password`) : `equipe@houseofchallenge.test` (interviewer), `aya@houseofchallenge.test` (participante, TikTok renseigné), `kofi@houseofchallenge.test` (sans TikTok).
Admin : `ADMIN_EMAIL` / `ADMIN_PASSWORD` du `.env`.
Production : `SEED_DEMO=false` (ou `APP_ENV=production`) pour ne semer que admin, formules et paramètres.

## Commandes

| | |
|---|---|
| `php artisan test` | tests (MySQL, base `house_of_challenge_test` à créer) |
| `npm run types` / `npx tsc --noEmit` | typage TypeScript |
| `npm run build` | build front |
| `php artisan hoc:maintenance` | holds expirés, réconciliation paiements, rappels, expiration crédits/publications |
| `php artisan schedule:work` | exécute le planificateur (toutes les 5 min) |

En production, appeler `/api/cron/maintenance` avec `Authorization: Bearer <CRON_SECRET>` toutes les 5 min, ou planifier `php artisan schedule:run` chaque minute.

## Paiement

`PAYMENT_PROVIDER=mock` : la page `/paiement/mock` simule succès/échec (désactivée en production).
Pour un vrai prestataire : implémenter `App\Services\Payments\PaymentProvider`, l'enregistrer dans `Providers::make()`, puis configurer dans son tableau de bord l'URL du webhook `https://<domaine>/api/webhooks/payment` et `PAYMENT_*` dans `.env`.

## Paiement manuel (espèces / transfert)

Admin → Paiements → « Saisir un paiement manuel » : l'achat est créé comme un paiement en ligne (crédits ajoutés, traçé dans `credit_ledger` et `audit_logs`).

Voir [DECISIONS.md](DECISIONS.md) pour les choix et les écarts avec le cahier des charges.
