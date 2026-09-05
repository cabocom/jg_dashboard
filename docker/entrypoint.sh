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

# Ajustar permisos
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/resources
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/resources

# Limpiar cualquier manifest o cache residual de bootstrap
rm -f /var/www/html/bootstrap/cache/*.php

# Sincronizar variables de entorno con prefijo WEB_
if [ -z "$APP_KEY" ] && [ -n "$WEB_APP_KEY" ]; then
    export APP_KEY="$WEB_APP_KEY"
fi
if [ -z "$APP_ENV" ] && [ -n "$WEB_APP_ENV" ]; then
    export APP_ENV="$WEB_APP_ENV"
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
