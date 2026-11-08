# Décisions d'implémentation

Stack retenue (demandée) : **Laravel 12 + React 19 (Inertia 2, TypeScript, Tailwind 4) + MySQL**. Cela remplace Next.js / Prisma / Auth.js du cahier des charges.

| Sujet | Décision |
|---|---|
| Auth | Authentification Laravel (session) du starter kit ; rôle en colonne `users.role`. Vérification d'e-mail non bloquante pour réserver, exigée pour **publier**. |
| Téléphone E.164 | Validation par expression régulière `+[1-9]\d{6,14}` (pas de libphonenumber côté serveur). |
| Modèle `Order` | Absent, comme prévu : `Purchase` joue ce rôle. `payments.booking_id` ajouté pour relier le paiement à la réservation créée au checkout. |
| Fuseau | Stockage UTC, affichage `Africa/Lome` (`DISPLAY_TIMEZONE`) + heure locale du navigateur si différente. |
| Paiement | Interface `PaymentProvider` + `MockPaymentProvider` (webhook signé HMAC). **Aucun adaptateur réel** (FedaPay/KKiaPay/CinetPay) : à ajouter dans `Providers::make()` (phase 12). |
| Webhook | `POST /api/webhooks/payment`, hors CSRF, signature vérifiée, idempotent (verrou + statut du `Payment`), montant contrôlé (écart ⇒ paiement `ECHOUE`, aucun crédit). |
| Réservation sans crédit | Hold du créneau → `Booking EN_ATTENTE_PAIEMENT` → paiement → webhook crédite l'achat puis consomme 1 crédit (FIFO) et confirme. Si le hold a expiré entre-temps, les crédits restent sur le compte. |
| Nouvelle réservation | Démarrer une réservation annule les réservations `EN_ATTENTE_PAIEMENT` précédentes du même utilisateur (libère leurs holds). |
| Annulation tardive | Au-delà du délai, l'utilisateur ne peut plus annuler (bouton désactivé) ; l'admin peut annuler et recréditer. |
| Cron | `php artisan hoc:maintenance` (planifié toutes les 5 min) **ou** `GET/POST /api/cron/maintenance` avec `Authorization: Bearer $CRON_SECRET`. |
| Rappels | 24 h : envoyé si le passage est dans (2 h, 24 h] ; 2 h : dans (0, 2 h]. Drapeaux en base ⇒ jamais deux fois. |
| Ajustement admin positif | Crée un achat « Ajustement admin » (0 FCFA, 90 jours) pour que les crédits aient une date d'expiration. |
| Suppression de compte | Anonymisation (nom, e-mail, téléphone, pseudo effacés, compte désactivé) ; réservations/paiements conservés. |
| E-mails | Un seul gabarit HTML (`emails/generic.blade.php`), `MAIL_MAILER=log` en dev. |
| Markdown (FAQ, OCD, CGU) | Rendu minimal (titres + paragraphes) côté React, sans HTML injecté. |
| Prestataires réels | `FedaPayProvider` (signature `X-FEDAPAY-SIGNATURE`) et `CinetPayProvider` (pas de signature fiable : le statut et le montant sont relus via `/payment/check`). Écrits d'après la doc publique, testés avec des réponses simulées, **non testés en sandbox**. KKiaPay non pris en charge : son paiement passe par un widget JS, pas par une redirection. |
| Couleurs | `#C8662B` sur blanc = contraste 3,8 (< 4,5 WCAG AA). Boutons et texte sur fond clair : `#B05A26` (4,8) ; texte sur anthracite : `#D4733A` (5,0). |
| Police | Police système au lieu d'une police web (supprime une requête bloquante : FCP mobile 3,2 s → 2,6–2,8 s). |
| Retour de paiement | Si le webhook a déjà confirmé la réservation, `/paiement/retour` redirige directement vers la confirmation. |
| Uploads | `UPLOAD_PROVIDER=local` (disque public) ou `cloudinary` (upload signé via l'API REST). |
| Erreurs | Sentry actif si `SENTRY_LARAVEL_DSN` est défini. Pages 403/404/419/429/500/503 en français. |
| Seed en production | Refuse de démarrer sans `ADMIN_EMAIL`/`ADMIN_PASSWORD` (pas d'admin par défaut) ; pas de données de démo. |
| Déploiement | Dockerfile + `docker-compose.prod.yml` (app, scheduler, MySQL). **Non construits ici** (Docker absent de la machine de dev). |
| Hors de portée sans accès | Mise en ligne effective, nom de domaine, comptes marchands, clés Sentry/Cloudinary/SMTP. |
