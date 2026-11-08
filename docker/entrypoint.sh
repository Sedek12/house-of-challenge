#!/bin/sh
set -e
cd /var/www/html

# Toujours repartir de la config de l'environnement (le seeder lit ADMIN_EMAIL / ADMIN_PASSWORD).
php artisan config:clear >/dev/null

# Le conteneur "app" applique les migrations ; le conteneur "scheduler" (RUN_MIGRATIONS=0) non.
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    php artisan migrate --force
    # Seed idempotent : admin, formules et paramètres (pas de données de démo en production).
    php artisan db:seed --force
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
