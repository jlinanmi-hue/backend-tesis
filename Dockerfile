FROM php:8.4-cli-alpine

# Instalar extensiones necesarias para Laravel, DomPDF y PostgreSQL
RUN apk add --no-cache \
    postgresql-dev \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath pcntl gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar el proyecto
COPY . .

# Instalar dependencias de producción
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Permisos para storage y cache
RUN chmod -R 777 storage bootstrap/cache

# Habilitar multi-threading / workers concurrentes en el servidor CLI de PHP
ENV PHP_CLI_SERVER_WORKERS=8

EXPOSE 10000

# Iniciar el servidor web con multi-workers en el puerto que Render define
CMD ["sh", "-c", "php artisan config:clear && php artisan route:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
