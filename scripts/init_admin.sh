#!/bin/sh
set -e

echo "==> [jg_dashboard] Inicializando usuario administrador..."
php artisan dashboard:init-admin "$@"
