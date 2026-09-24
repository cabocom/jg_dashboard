#!/bin/sh
set -e
umask 022

echo "🚀 [jG Dashboard] Inicializando contenedor web..."

# Crear directorios de cache, vistas y logs si no existen
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache \
         /var/www/html/resources/views

# Ajustar permisos de directorios de Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/resources
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/resources

# Asegurar directorios de Nginx y permisos para www-data (evitar error 13 permission denied en buffers)
mkdir -p /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/fastcgi \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/scgi \
         /var/lib/nginx/tmp/uwsgi \
         /var/log/nginx
chown -R www-data:www-data /var/lib/nginx /var/log/nginx
chmod -R 775 /var/lib/nginx /var/log/nginx

# Limpiar cualquier manifest o cache residual de bootstrap
rm -f /var/www/html/bootstrap/cache/*.php

# Sincronizar variables de entorno con prefijo WEB_
if [ -z "$APP_KEY" ] && [ -n "$WEB_APP_KEY" ]; then
    export APP_KEY="$WEB_APP_KEY"
fi
if [ -z "$APP_ENV" ] && [ -n "$WEB_APP_ENV" ]; then
    export APP_ENV="$WEB_APP_ENV"
fi

# Validación de seguridad estricta para entornos no locales (staging, production, etc.)
CURRENT_ENV="${APP_ENV:-production}"
if [ "$CURRENT_ENV" != "local" ] && [ "$CURRENT_ENV" != "testing" ]; then
    echo "🔒 [Seguridad] Validando configuración y secretos obligatorios para entorno: $CURRENT_ENV..."

    # 1. Validar APP_KEY obligatoria y no predecible
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:3vKjQ+7eZk1A9jH2wP5T6m8V0xY1Z4aB7c8D9E0F1G2=" ]; then
        echo "❌ [ERROR CRÍTICO] APP_KEY no está configurada o utiliza la clave predeterminada insegura. Genere una clave con 'php artisan key:generate --show'." >&2
        exit 1
    fi

    # 2. Validar DB_PASSWORD obligatoria y no predecible
    if [ -z "$DB_PASSWORD" ] || [ "$DB_PASSWORD" = "postgres_secure_pass" ] || [ "$DB_PASSWORD" = "postgres" ]; then
        echo "❌ [ERROR CRÍTICO] DB_PASSWORD no está configurada o utiliza un valor por defecto inseguro ('postgres_secure_pass'). Configure una contraseña robusta." >&2
        exit 1
    fi

    # 3. Validar ADMIN_DEFAULT_PASSWORD obligatoria y segura (mínimo 16 caracteres, no defaults)
    if [ -z "$ADMIN_DEFAULT_PASSWORD" ] || [ "$ADMIN_DEFAULT_PASSWORD" = "admin1234567890!" ] || [ "$ADMIN_DEFAULT_PASSWORD" = "admin_secure_password_16chars!" ]; then
        echo "❌ [ERROR CRÍTICO] ADMIN_DEFAULT_PASSWORD no está configurada o utiliza un valor de prueba predecible. Configure una contraseña segura de al menos 16 caracteres." >&2
        exit 1
    fi

    if [ ${#ADMIN_DEFAULT_PASSWORD} -lt 16 ]; then
        echo "❌ [ERROR CRÍTICO] ADMIN_DEFAULT_PASSWORD debe tener al menos 16 caracteres para garantizar la robustez del usuario administrador (CWE-521)." >&2
        exit 1
    fi

    # 4. Desactivar APP_DEBUG en producción (CWE-215)
    if [ "$APP_DEBUG" = "true" ] || [ "$APP_DEBUG" = "1" ]; then
        echo "⚠️ [Seguridad] APP_DEBUG detectado como 'true' en entorno no local. Forzando APP_DEBUG=false para evitar fugas de información."
        export APP_DEBUG="false"
    fi
fi

# Descubrir paquetes instalados de Laravel
echo "🔍 Descubriendo paquetes de Laravel..."
php artisan package:discover --ansi || true

# Publicar assets estáticos de Filament y Livewire
echo "🎨 Publicando assets estáticos (Filament y Livewire)..."
php artisan filament:assets || true
php artisan livewire:publish --assets || true

# Migración de tabla users/sessions y seeder en producción
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$WEB_RUN_MIGRATIONS" = "true" ] || [ "$APP_ENV" = "production" ] || [ "$WEB_APP_ENV" = "production" ]; then
    echo "📦 Ejecutando migraciones de base de datos..."
    php artisan migrate --force --graceful || true
    echo "🌱 Ejecutando seeders de usuario administrador SRE..."
    php artisan db:seed --force || true
fi

# Limpiar y preparar carpetas de storage y vistas
echo "⚡ Limpiando y preparando caché de Laravel..."
rm -rf /var/www/html/storage/framework/views/* /var/www/html/storage/framework/cache/data/*
php artisan optimize:clear || true
php artisan view:cache || true


# Permisos finales sobre public, storage y bootstrap/cache (775 restrictivo para www-data)
chown -R www-data:www-data /var/www/html/public /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ [jG Dashboard] Listo. Iniciando supervisord..."
exec "$@"
