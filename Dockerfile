# ==============================================================================
# STAGE 1: Dependency Builder
# ==============================================================================
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ==============================================================================
# STAGE 2: Production Runtime (PHP 8.3-FPM + Nginx on Alpine)
# ==============================================================================
FROM php:8.3-fpm-alpine

LABEL maintainer="Javier Gálvez <cabocom>"
LABEL description="jG Dashboard - Control Plane, Governance & HITL Management Panel"

# Dependencias del sistema y librerías PostgreSQL
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    libpq \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    curl

# Compilar extensiones PHP necesarias
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/include/postgresql/ \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        opcache \
        pcntl \
        intl \
        bcmath

# Configuración de Nginx, PHP y Supervisord
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar aplicación y dependencias compiladas
COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor

# Asegurar directorios de almacenamiento y permisos
RUN mkdir -p \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
        /var/log/nginx \
        /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=10s \
    CMD curl -f http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
