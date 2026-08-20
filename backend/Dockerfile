# Dockerfile listo para cuando dockericen el backend (producción o
# entorno unificado). En desarrollo se recomienda correr Laravel de
# forma nativa con `composer run dev` para conservar el hot-reload de
# Vite — ver README.md, sección "¿Por qué el backend no corre en
# Docker desde el día uno?".
#
# Build en dos etapas: compila los assets de Vue/Vite y luego los
# sirve junto con PHP-FPM.

FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.3-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
