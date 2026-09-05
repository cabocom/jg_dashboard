#!/usr/bin/env bash
# ==============================================================================
# Script de Verificación Integral de Calidad para jg_dashboard (Laravel 11 / Pest)
# ==============================================================================

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

echo "========================================"
echo "🔍 Ejecutando verificación de jg_dashboard..."
echo "========================================"

echo ""
echo "1️⃣  [Pest] Suite de pruebas PHP y Filament..."
if [ -f "./vendor/bin/pest" ]; then
    ./vendor/bin/pest "$@"
elif [ -f "./vendor/bin/phpunit" ]; then
    ./vendor/bin/phpunit "$@"
else
    php artisan test "$@"
fi

echo ""
echo "========================================"
echo "✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!"
echo "========================================"
