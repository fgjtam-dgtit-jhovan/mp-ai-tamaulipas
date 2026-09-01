# Producción simple para Laravel.
# Los assets de Vite se construyen fuera del contenedor (en el host o CI),
# porque Wayfinder ejecuta `php artisan wayfinder:generate` y eso falla si se
# intenta compilar dentro del stage Node sin el entorno Laravel completo.

FROM php:8.3-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    zip \
    unzip \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        mbstring \
        tokenizer \
        intl \
        dom \
        fileinfo \
        session

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
