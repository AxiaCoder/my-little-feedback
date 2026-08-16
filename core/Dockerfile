FROM php:8.3-cli-alpine

RUN apk add --no-cache icu-dev postgresql-dev libzip-dev unzip git \
    && docker-php-ext-install intl pdo_pgsql zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

# router.php is required, not decorative: without it the built-in server 404s on
# any URI containing a dot before reaching Symfony. See the file's header.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "router.php"]
