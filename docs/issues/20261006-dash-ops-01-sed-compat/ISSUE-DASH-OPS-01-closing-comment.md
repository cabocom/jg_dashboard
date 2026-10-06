# Comentario de Cierre: [DASH-OPS-01] Incompatibilidad del Script restart_services.sh en Sistemas Linux

Se ha corregido la incompatibilidad de `sed -i` entre macOS y Linux en los scripts de operaciones y reinicio del ecosistema.

### Resumen de Cambios:
1. **Helper Portable `run_sed_i`:**
   - Detecta dinámicamente si el comando `sed` disponible corresponde a GNU `sed` (Linux / servidores de producción) o BSD `sed` (macOS), aplicando la sintaxis correspondiente (`sed -i` vs `sed -i ''`).
2. **Corrección de Scripts:**
   - Actualizado `scripts/core/restart_services.sh` en todas las ediciones de variables `.env`.
   - Actualizado `scripts/core/install_prod.sh` garantizando portabilidad bidireccional.
3. **Validación:**
   - Verificado el correcto funcionamiento tanto en macOS host como en un contenedor Docker con Linux Ubuntu.
