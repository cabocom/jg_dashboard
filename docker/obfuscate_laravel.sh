#!/bin/sh
set -e

echo "🔒 [YAKPRO-PO] Preparando entorno de ofuscación AST para Laravel..."

YAKPRO_DIR="/opt/yakpro-po"

if [ ! -d "$YAKPRO_DIR" ]; then
    echo "📥 Descargando Yakpro-Po..."
    git clone --depth 1 https://github.com/pk-fr/yakpro-po.git "$YAKPRO_DIR"
fi

if [ ! -d "$YAKPRO_DIR/PHP-Parser" ]; then
    echo "🔗 Enlazando PHP-Parser desde vendor local..."
    ln -s /stage/vendor/nikic/php-parser "$YAKPRO_DIR/PHP-Parser"
fi

CONFIG_FILE="/stage/docker/yakpro-po.cnf"

# Directorios troncales a ofuscar en Laravel (app y routes)
for dir in app routes; do
    if [ -d "/stage/$dir" ]; then
        echo "⚡ [YAKPRO-PO] Procesando y ofuscando /stage/$dir..."
        mkdir -p "/tmp/obf_$dir"
        php "$YAKPRO_DIR/yakpro-po.php" "/stage/$dir" -o "/tmp/obf_$dir" --config-file "$CONFIG_FILE" -s
        rm -rf "/stage/$dir"
        mv "/tmp/obf_$dir/yakpro-po/obfuscated" "/stage/$dir"
        rm -rf "/tmp/obf_$dir"
        echo "  ✓ $dir ofuscado correctamente."
    fi
done

echo "✅ [YAKPRO-PO] Ofuscación de Laravel 11 completada exitosamente."
