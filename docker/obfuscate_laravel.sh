#!/bin/sh
set -e

echo "🔒 [YAKPRO-PO] Preparando entorno de ofuscación AST para Laravel..."

YAKPRO_DIR="/opt/yakpro-po"

if [ ! -d "$YAKPRO_DIR" ]; then
    echo "📥 Descargando Yakpro-Po y motor PHP-Parser..."
    git clone --depth 1 https://github.com/pk-fr/yakpro-po.git "$YAKPRO_DIR"
    cd "$YAKPRO_DIR"
    git clone --depth 1 --branch v4.18.0 https://github.com/nikic/PHP-Parser.git include/php-parser
fi

CONFIG_FILE="/stage/docker/yakpro-po.cnf"

# Directorios troncales a ofuscar
for dir in app routes config; do
    if [ -d "/stage/$dir" ]; then
        echo "⚡ [YAKPRO-PO] Procesando y ofuscando /stage/$dir..."
        mkdir -p "/tmp/obf_$dir"
        php "$YAKPRO_DIR/yakpro-po.php" "/stage/$dir" -o "/tmp/obf_$dir" --config-file "$CONFIG_FILE" -s
        rm -rf "/stage/$dir"
        mv "/tmp/obf_$dir" "/stage/$dir"
        echo "  ✓ $dir ofuscado correctamente."
    fi
done

echo "✅ [YAKPRO-PO] Ofuscación de Laravel 11 completada exitosamente."
