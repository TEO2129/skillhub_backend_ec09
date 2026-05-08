# Stage 1 : builder
FROM php:8.4-cli-bookworm AS builder

# Utiliser mlocati/docker-php-extension-installer
# Ce script télécharge des extensions pré-compilées depuis GitHub
# → ne dépend PAS de deb.debian.org
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        mongodb \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --optimize-autoloader --no-dev

COPY . .
RUN php artisan package:discover --ansi || true
RUN composer dump-autoload --optimize

# ─────────────────────────────────────────────────────────────
# Stage 2 : runtime
# ─────────────────────────────────────────────────────────────
FROM php:8.4-cli-bookworm AS runtime

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        mongodb \
        zip

WORKDIR /var/www/html

COPY --from=builder /app /var/www/html

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]