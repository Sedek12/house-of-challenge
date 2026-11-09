# Décisions d'implémentation

Référence : **Cahier des charges — Site d'inscription OCD Live / House of Challenge** (V1, 8 nov. 2026). Exigences citées : F1–F10 (vendeur), A1–A10 (tableau de bord).

Stack : **Laravel 12 + React 19 (Inertia 2, TypeScript, Tailwind 4) + MySQL**.

## Écarts assumés avec le cahier des charges

| Sujet | Cahier des charges (§7) | Choix | Pourquoi |
|---|---|---|---|
| Frontend / API | React Router + API REST + Sanctum | **Inertia** (monolithe) : pages React servies par Laravel, authentification de session | Un seul déploiement, pas d'API à maintenir ni de jeton à gérer ; même rendu pour le vendeur. **À faire valider par le client.** |
| Anti-double réservation | Contrainte d'unicité + transaction avec verrou | Mise à jour conditionnelle atomique du créneau **et** index unique `bookings.active_slot_id` (rempli tant que la réservation occupe le créneau, `NULL` sinon) | Deux protections : la première évite l'erreur, la seconde garantit en base qu'aucun créneau n'a deux réservations actives. |
| Package « Multi-sessions » (3 passages) | Exemple de package | Créé **inactif** | Il faut réserver plusieurs créneaux en une fois ; le contenu des packages est à valider avec le client (§9). |

## Règles métier retenues

| Sujet | Décision |
|---|---|
| Comptes équipe | `ADMIN` et `INTERVIEWER` (animateur en lecture seule). |
| Comptes vendeurs (**écart : prévus en V2 dans le cahier, ajoutés à la demande du porteur du projet**) | Optionnels : la réservation sans compte reste possible (objectif < 3 min). Le vendeur crée son compte à part ou pendant la réservation (case + mot de passe). Rôle `VENDOR`, sans accès au tableau de bord ni à l'espace animateur. Pas de vérification d'e-mail : les anciennes réservations faites sans compte ne sont donc **pas** rattachées automatiquement à un nouveau compte. |
| Boutiques et produits mémorisés | Un vendeur a une ou plusieurs boutiques (`shops`), enregistrées une seule fois. À la réservation, il choisit sa boutique : ses informations et ses produits (photos comprises, copiées) sont repris ; ses coordonnées viennent du compte (nom, téléphone et e-mail ne sont pas redemandés). Le catalogue de la boutique (`shop_products`) = les produits de la dernière réservation ou de la dernière modification via le lien de suivi. |
| Pays et téléphone | Liste de pays (drapeau, nom, indicatif) définie dans `App\Support\Countries`. Le passage sur place n'est possible que depuis le Togo ; ailleurs, uniquement à distance (vérifié aussi côté serveur). Le numéro est saisi avec l'indicatif du pays et stocké au format international. |
| Parcours de réservation | 4 étapes : package (un clic le choisit, les autres packages disparaissent, « Changer » y ramène), créneau (pays, jour/heure, mode), informations (boutique, activité, produits), paiement. Les créneaux déjà pris restent visibles, grisés « Occupé ». |
| Réservation | Une réservation = un package = un créneau. Le vendeur saisit un formulaire unique (F4), 1 à N produits selon le package (F5), des consignes de 500 caractères (F6) et accepte le traitement de ses données (loi n° 2019-014). |
| Numéro et lien | Chaque réservation a une référence `OCD-XXXXXX` et un jeton de suivi de 40 caractères (`/suivi/{jeton}`, non indexé). La même page sert après le paiement et via le lien (F8, F9). |
| Blocage (F3) | 15 min (réglage `hold_minutes`) dès la validation du formulaire. Un blocage expiré est libéré par le cron **et** juste avant qu'un autre vendeur réserve le même créneau. |
| Créneaux libres (F2) | Affichés sur 30 jours maximum ; un créneau en attente de paiement ou confirmé n'est proposé à personne. |
| Paiement (F7) | Interface `PaymentProvider` : `mock` (dev), `fedapay`, `cinetpay`. La confirmation ne vient **que** du webhook signé (ou de la réconciliation / saisie manuelle de l'admin). La page de retour ne valide jamais. Paiement non abouti ⇒ réservation annulée, créneau libéré. |
| Modification par le vendeur (F9) | Produits et consignes modifiables jusqu'à 24 h avant le live (`edit_deadline_hours`). |
| Report / annulation (A9) | Par l'admin uniquement ; l'annulation libère le créneau immédiatement ; remboursement hors du site. Report gratuit si demandé ≥ 48 h avant (réglage `cancel_deadline_hours`, appliqué par l'équipe via WhatsApp). |
| Statuts (A5) | `EN_ATTENTE_PAIEMENT`, `CONFIRMEE`, `EFFECTUEE`, `ABSENT`, `ANNULEE`. « Confirmée » est automatique après paiement ; « Effectuée » / « Absent » sont posés par l'admin. |
| Créneaux (A3) | Un « live » = une plage horaire découpée en créneaux (durée et pause réglables). Ouverture à une date précise ou par modèle hebdomadaire (jours + nombre de semaines). |
| Animateur (A6) | Planning jour/semaine et fiche live par vendeur (précédent/suivant dans la journée), sans aucune action d'écriture. |
| Fuseau | Stockage UTC, affichage `Africa/Lome`. |
| Photos produits | Réduites côté navigateur (1000 px, WebP) avant l'envoi, puis redimensionnées (800 px) et converties en WebP côté serveur. Disque `public` ; photo facultative. |
| E-mails | Gabarit HTML unique ; confirmation = récapitulatif + référence + lien de suivi + fichier agenda. `MAIL_MAILER=log` en dev. |
| WhatsApp | Liens `wa.me` uniquement (V1), numéro dans les paramètres. Pas de rappels automatiques (V2). |
| Cron | `php artisan hoc:maintenance` chaque minute (`schedule:run`) **ou** `GET/POST /api/cron/maintenance` avec `Authorization: Bearer $CRON_SECRET`. |
| Couleurs | `#B05A26` sur fond clair (contraste 4,8) ; `#D4733A` sur fond anthracite (5,0). |
| Police | Police système (aucune requête bloquante). |
| Erreurs | Sentry si `SENTRY_LARAVEL_DSN` est défini ; pages 403/404/419/429/500/503 en français. |
| Seed en production | Refuse de démarrer sans `ADMIN_EMAIL`/`ADMIN_PASSWORD` ; pas de données de démo. |

## Hors périmètre V1 (retiré)

Crédits, formules à validité, projets publiés, vitrine publique, modération de publications, journal d'audit consultable (les actions admin restent tracées en base), rappels automatiques, formulaire de contact (remplacé par le bouton WhatsApp). La migration `2025_01_01_000400_realign_cdc_v1` supprime ces tables ; les données de démonstration précédentes ne sont pas conservées (aucune mise en production à ce jour).

## Reste à faire / à valider

- **Compte marchand de paiement.** Les clés FedaPay `live` actuellement dans `.env` proviennent d'un autre projet (StockFlow) : les paiements arrivent donc sur ce compte, et non sur un compte au nom d'OCD Live comme le prévoit le cahier (§7). À remplacer par les clés d'OCD Live avant la mise en production. Le second agrégateur (FeexPay) n'a pas d'adaptateur.
- **Webhook FedaPay.** Déclarer `https://<domaine>/api/webhooks/payment` dans le tableau de bord FedaPay et renseigner `PAYMENT_WEBHOOK_SECRET` (sans secret, toute notification est refusée).

- Agrégateur : choix par le client (FedaPay ou PayGate Global) et compte marchand au nom d'OCD Live. `FedaPayProvider` et `CinetPayProvider` suivent la doc publique mais **n'ont pas été testés en sandbox** ; PayGate Global n'a pas d'adaptateur. Flooz et T-Money doivent être confirmés comme moyens proposés par l'agrégateur retenu.
- Réglages A10 manquants : logo et couleurs modifiables depuis l'admin.
- Contenus réels : packages, mentions légales, FAQ, contacts.
- Mesures de légèreté (§5) : Lighthouse mobile ≥ 90, < 500 Ko par page, test sur Android d'entrée de gamme.
- Sauvegarde quotidienne de la base, conservée 7 jours, et test de restauration.
- Déploiement : Dockerfile et `docker-compose.prod.yml` fournis mais non construits ici.
