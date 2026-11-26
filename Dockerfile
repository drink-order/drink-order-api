# Laravel API image for Render (or any Docker host)
FROM php:8.4-cli

# System libs + PHP extensions (pdo_pgsql for Supabase Postgres, zip for composer, opcache for speed)
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Production php.ini + settings for the built-in server
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "opcache.enable_cli=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "upload_max_filesize=5M"; \
        echo "post_max_size=8M"; \
        echo "memory_limit=256M"; \
    } > "$PHP_INI_DIR/conf.d/app.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first so this layer is cached between deploys
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Copy the app and build the optimized autoloader (runs package:discover)
COPY . .
RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction

# Parallel workers for `php artisan serve` (the frontend polls, so one worker is not enough)
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 10000

# NOTE: no `php artisan config:cache` — app code calls env() directly
# (SupabaseStorageService, TwilioService, AuthController), which returns null once config is cached.
# Set RUN_SEEDERS=true for one deploy to load demo data, then remove it.
CMD php artisan migrate --force \
    && if [ "$RUN_SEEDERS" = "true" ]; then php artisan db:seed --force; fi \
    && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
