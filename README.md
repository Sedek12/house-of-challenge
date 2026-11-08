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
| `php artisan test` | tests unitaires/HTTP (MySQL, base `house_of_challenge_test` à créer) |
| `npm run test:e2e` | tests E2E Playwright (`npx playwright install chromium` une fois). **Réinitialise la base de dev** (`migrate:fresh --seed`) |
| `npm run typecheck` | typage TypeScript |
| `npm run build` | build front |
| `php artisan hoc:maintenance` | holds expirés, réconciliation paiements, rappels, expiration crédits/publications |
| `php artisan schedule:work` | exécute le planificateur (toutes les 5 min) |

En production, appeler `/api/cron/maintenance` avec `Authorization: Bearer <CRON_SECRET>` toutes les 5 min, ou planifier `php artisan schedule:run` chaque minute.

## Paiement

| `PAYMENT_PROVIDER` | Usage |
|---|---|
| `mock` | Dev uniquement : `/paiement/mock` simule succès/échec. Interdit en production. |
| `fedapay` | `PAYMENT_SECRET_KEY` (clé secrète), `PAYMENT_WEBHOOK_SECRET`, `PAYMENT_ENV=sandbox|live`. |
| `cinetpay` | `PAYMENT_PUBLIC_KEY` = site_id, `PAYMENT_SECRET_KEY` = apikey. Le statut est toujours re-vérifié via l'API. |

Les adaptateurs FedaPay et CinetPay suivent la documentation publique mais **n'ont pas encore été testés en sandbox** : faire un paiement sandbox complet avant la mise en production.

## Déploiement (VPS avec Docker)

1. Sur le serveur : `cp .env.production.example .env`, remplir les valeurs (`APP_KEY` via `php artisan key:generate --show`, mots de passe forts, `ADMIN_EMAIL`/`ADMIN_PASSWORD`, clés de paiement, SMTP, Cloudinary).
2. `docker compose -f docker-compose.prod.yml --env-file .env up -d --build`
   - `app` : migrations + seed (admin, formules, paramètres ; **aucune donnée de démo**), puis Apache sur le port 8080.
   - `scheduler` : `schedule:work` (maintenance toutes les 5 min).
   - `db` : MySQL 8.4 (volume persistant).
3. Placer un reverse proxy HTTPS devant le port 8080 (Caddy : `votre-domaine.com { reverse_proxy localhost:8080 }`).
4. Dans le tableau de bord du prestataire, déclarer l'URL de webhook `https://votre-domaine.com/api/webhooks/payment`.
5. Tester un paiement en sandbox de bout en bout, puis `PAYMENT_ENV=live` après validation par le porteur du projet et `docker compose ... up -d`.

Hébergement mutualisé sans Docker : `composer install --no-dev`, `npm ci && npm run build`, document root sur `public/`, `php artisan migrate --force && php artisan db:seed --force`, et une tâche cron `* * * * * php /chemin/artisan schedule:run`.

### Créer un autre administrateur
Admin → Utilisateurs → changer le rôle en `ADMIN` (action tracée dans le journal d'audit).

## Paiement manuel (espèces / transfert)

Admin → Paiements → « Saisir un paiement manuel » : l'achat est créé comme un paiement en ligne (crédits ajoutés, traçé dans `credit_ledger` et `audit_logs`).

Voir [DECISIONS.md](DECISIONS.md) pour les choix et les écarts avec le cahier des charges.
