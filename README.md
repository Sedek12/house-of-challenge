# OCD Live / House of Challenge — site de réservation

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

Le vendeur réserve **sans compte** (`/reserver`) et reçoit un lien de suivi. Seule l'équipe se connecte (`/login`) :
- Admin : `ADMIN_EMAIL` / `ADMIN_PASSWORD` du `.env` (valeurs de `.env.example` : `admin@ocdlive.test` / `Admin#12345`).
- Animateur (lecture seule) : `animateur@ocdlive.test` / `password` (données de démo).
Production : `SEED_DEMO=false` (ou `APP_ENV=production`) pour ne semer que admin, formules et paramètres.

## Commandes

| | |
|---|---|
| `php artisan test` | tests unitaires/HTTP : lisent `.env.testing` (et non `.env`) → base locale `house_of_challenge_test` (à créer), paiement simulé, e-mails en mémoire. Un test échoue si ce n'est plus le cas. |
| `npm run test:e2e` | tests E2E Playwright (`npx playwright install chromium` une fois). Utilisent leur **propre base locale** `house_of_challenge_e2e` (créée automatiquement, réinitialisée à chaque test), paiement simulé et aucun e-mail réel, quel que soit `.env`. Refusent de s'exécuter si la base n'est pas sur `127.0.0.1`. |
| `npm run typecheck` | typage TypeScript |
| `npm run build` | build front |
| `php artisan hoc:maintenance` | libère les créneaux bloqués dont le paiement a expiré, réconcilie les paiements en attente |
| `php artisan schedule:work` | exécute le planificateur (chaque minute) |

En production, appeler `/api/cron/maintenance` avec `Authorization: Bearer <CRON_SECRET>` chaque minute, ou planifier `php artisan schedule:run` chaque minute.

## Paiement

| `PAYMENT_PROVIDER` | Usage |
|---|---|
| `mock` | Dev uniquement : `/paiement/mock` simule succès/échec. Interdit en production. |
| `fedapay` | `PAYMENT_SECRET_KEY` (clé secrète), `PAYMENT_WEBHOOK_SECRET`, `PAYMENT_ENV=sandbox|live`. |
| `cinetpay` | `PAYMENT_PUBLIC_KEY` = site_id, `PAYMENT_SECRET_KEY` = apikey. Le statut est toujours re-vérifié via l'API. |

Les adaptateurs FedaPay et CinetPay suivent la documentation publique mais **n'ont pas encore été testés en sandbox** : faire un paiement sandbox complet avant la mise en production.

### Brancher FedaPay (sandbox)

1. Dans le tableau de bord FedaPay, mode **Sandbox** : copier la clé secrète (`sk_sandbox_…`).
2. Y créer un **webhook** vers `https://votre-domaine/api/webhooks/payment` et copier son secret.
3. Dans `.env` : `PAYMENT_PROVIDER=fedapay`, `PAYMENT_ENV=sandbox`, `PAYMENT_SECRET_KEY=…`, `PAYMENT_WEBHOOK_SECRET=…`, puis `php artisan config:clear`.
   **Mode `live` = paiements réels.** Avec des clés `sk_live_…`, un paiement lancé depuis le site débite vraiment le client : utiliser un petit montant pour les essais.
   Le secret du webhook est obligatoire pour que les notifications soient acceptées : sans lui elles sont toutes refusées (la confirmation passe alors par la vérification serveur à serveur).
   Sous Windows/WAMP, si les appels échouent avec « SSL certificate problem », placer `cacert.pem` dans `storage/certs/` (déjà géré par l'application).
4. En local, FedaPay ne peut pas joindre `localhost` : la notification (webhook) n'arrive pas, mais quand le vendeur revient sur le site, le serveur interroge FedaPay directement et confirme si le paiement est approuvé. Pour tester le webhook lui-même, exposer le site avec un tunnel (ngrok, Cloudflare Tunnel).

La réservation n'est confirmée que par la réponse de FedaPay (webhook signé ou interrogation serveur), jamais par la page de retour seule.

## E-mails

- `MAIL_MAILER=log` (par défaut en dev) : les e-mails sont écrits dans `storage/logs/laravel.log`.
- En production : `MAIL_MAILER=smtp` et les identifiants SMTP du fournisseur (Brevo, Resend…), voir `.env.production.example`.
- À chaque **réservation confirmée** (paiement reçu), l'équipe reçoit une alerte avec le vendeur, la boutique, le créneau et un lien vers la fiche. Les destinataires se règlent dans Admin → Paramètres (« E-mail qui reçoit les nouvelles réservations », plusieurs adresses séparées par des virgules) ; à défaut, les administrateurs sont prévenus. Le vendeur reçoit sa propre confirmation avec son lien de suivi.
- Un échec d'envoi ne bloque jamais une réservation (il est écrit dans les journaux).

## Déploiement (VPS avec Docker)

1. Sur le serveur : `cp .env.production.example .env`, remplir les valeurs (`APP_KEY` via `php artisan key:generate --show`, mots de passe forts, `ADMIN_EMAIL`/`ADMIN_PASSWORD`, clés de paiement, SMTP).
2. `docker compose -f docker-compose.prod.yml --env-file .env up -d --build`
   - `app` : migrations + seed (admin, formules, paramètres ; **aucune donnée de démo**), puis Apache sur le port 8080.
   - `scheduler` : `schedule:work` (maintenance toutes les 5 min).
   - `db` : MySQL 8.4 (volume persistant).
3. Placer un reverse proxy HTTPS devant le port 8080 (Caddy : `votre-domaine.com { reverse_proxy localhost:8080 }`).
4. Dans le tableau de bord du prestataire, déclarer l'URL de webhook `https://votre-domaine.com/api/webhooks/payment`.
5. Tester un paiement en sandbox de bout en bout, puis `PAYMENT_ENV=live` après validation par le porteur du projet et `docker compose ... up -d`.

Hébergement mutualisé sans Docker : `composer install --no-dev`, `npm ci && npm run build`, document root sur `public/`, `php artisan migrate --force && php artisan db:seed --force`, et une tâche cron `* * * * * php /chemin/artisan schedule:run`.

## Paiement hors ligne

Admin → Paiements → « Saisir un paiement hors ligne » : une réservation en attente de paiement est confirmée (espèces ou transfert reçu).

Voir [DECISIONS.md](DECISIONS.md) pour les choix, les écarts avec le cahier des charges et le reste à faire.
