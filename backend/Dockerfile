# ============================================================
# MP AI Tamaulipas - Laravel Backend
# PHP 8.5 CLI + SQL Server ODBC Driver 18
# Laravel + Inertia/Vue + Vite
# ============================================================

FROM php:8.5-cli-bookworm

ARG DEBIAN_FRONTEND=noninteractive

# ============================================================
# Variables de entorno
# ============================================================

ENV TZ=America/Monterrey \
    COMPOSER_ALLOW_SUPERUSER=1 \
    ACCEPT_EULA=Y \
    APP_ENV=production

WORKDIR /var/www/html

# ============================================================
# Dependencias del sistema
# ============================================================

RUN set -eux; \
    if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i \
            -e 's|http://deb.debian.org/debian|https://deb.debian.org/debian|g' \
            -e 's|http://deb.debian.org/debian-security|https://deb.debian.org/debian-security|g' \
            /etc/apt/sources.list.d/debian.sources; \
    fi; \
    if [ -f /etc/apt/sources.list ]; then \
        sed -i \
            -e 's|http://deb.debian.org/debian|https://deb.debian.org/debian|g' \
            -e 's|http://deb.debian.org/debian-security|https://deb.debian.org/debian-security|g' \
            /etc/apt/sources.list; \
    fi; \
    rm -rf /var/lib/apt/lists/*; \
    apt-get clean; \
    apt-get update -o Acquire::Retries=5; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        git \
        unzip \
        zip \
        libzip-dev \
        libpq-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libssl-dev \
        unixodbc \
        unixodbc-dev \
        odbcinst; \
    rm -rf /var/lib/apt/lists/*

# ============================================================
# Microsoft ODBC Driver 18 for SQL Server
# ============================================================

RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
    | gpg --dearmor \
    -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
    > /etc/apt/sources.list.d/microsoft-prod.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
        msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg

RUN docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo \
        pdo_pgsql \
        zip

# ============================================================
# SQL Server
# ============================================================

RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && rm -rf /tmp/pear

# ============================================================
# Composer
# ============================================================

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ============================================================
# Node.js 20
# ============================================================

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# Verificación del entorno
# ============================================================

RUN php -v \
    && php -m | grep -E 'pdo_sqlsrv|sqlsrv' \
    && php -r 'var_dump(extension_loaded("pdo_sqlsrv"));' \
    && odbcinst -q -d | grep -i "ODBC Driver 18 for SQL Server" \
    && composer --version \
    && node --version \
    && npm --version

# ============================================================
# Configuración PHP producción
# ============================================================

RUN { \
        echo "memory_limit=512M"; \
        echo "upload_max_filesize=50M"; \
        echo "post_max_size=50M"; \
        echo "max_execution_time=300"; \
        echo "max_input_time=300"; \
        echo "max_file_uploads=50"; \
        echo "date.timezone=America/Monterrey"; \
        echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/production.ini

# ============================================================
# OPcache
# ============================================================

RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.revalidate_freq=0"; \
        echo "opcache.save_comments=1"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ============================================================
# Dependencias Laravel
# ============================================================

# ============================================================
# Composer
# ============================================================

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress \
    --no-scripts


# ============================================================
# Node / Frontend
# ============================================================

COPY package*.json ./

RUN npm ci \
    --no-audit \
    --no-fund


# ============================================================
# Application
# ============================================================

# ============================================================
# Application
# ============================================================

COPY . .


# ============================================================
# Laravel directories
# ============================================================

RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data \
        bootstrap/cache \
        storage \
    && chmod -R 775 \
        bootstrap/cache \
        storage


# ============================================================
# Laravel Autoload
# ============================================================

RUN composer dump-autoload \
    --optimize \
    --no-dev

# ============================================================
# Build frontend
# ============================================================

RUN npm run build

# ============================================================
# Limpieza de dependencias Node
# ============================================================

RUN rm -rf node_modules \
    && npm cache clean --force


# ============================================================
# Permisos
# ============================================================

RUN chown -R www-data:www-data \
        storage \
        bootstrap/cache

RUN chmod -R ug+rwx \
        storage \
        bootstrap/cache

# ============================================================
# Limpiar caches durante BUILD
# ============================================================

RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear || true

# ============================================================
# Puerto
# ============================================================

EXPOSE 8000

# ============================================================
# Healthcheck Laravel
# ============================================================

HEALTHCHECK \
    --interval=30s \
    --timeout=10s \
    --start-period=60s \
    --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/up || exit 1

# ============================================================
# Comando por defecto
#
# IMPORTANTE:
# queue-worker y scheduler sobrescriben este CMD
# desde docker-compose.yml.
# ============================================================

CMD ["php", "artisan", "serve","--host=0.0.0.0","--port=8000"]
