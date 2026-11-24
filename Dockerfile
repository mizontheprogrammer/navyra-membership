FROM php:8.5-apache AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libonig-dev libpq-dev supervisor ca-certificates \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql intl mbstring zip bcmath pcntl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

FROM php-base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress --optimize-autoloader

FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php-base AS runtime
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY render-apache.conf /etc/apache2/sites-available/000-default.conf
COPY render-supervisor.conf /etc/supervisor/conf.d/navyra.conf
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x render-start.sh \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/security-production.conf \
    && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nmemory_limit=128M\nopcache.enable=1\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/production.ini
EXPOSE 10000
CMD ["/var/www/html/render-start.sh"]
