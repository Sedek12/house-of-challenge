# --- Build du front (Vite) ---
FROM node:22-alpine AS front
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
# Ziggy est importé depuis vendor/ par Vite : on installe les dépendances PHP dans une étape dédiée.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache php83 php83-phar php83-mbstring php83-openssl php83-tokenizer php83-fileinfo php83-dom php83-xml php83-xmlwriter php83-curl php83-iconv \
    && ln -sf /usr/bin/php83 /usr/bin/php \
    && composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs \
    && npm run build

# --- Image d'exécution ---
FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libicu-dev unzip \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=front /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
