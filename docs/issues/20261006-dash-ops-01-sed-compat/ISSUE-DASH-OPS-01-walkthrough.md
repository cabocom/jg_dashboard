# Walkthrough: [DASH-OPS-01] Incompatibilidad del Script restart_services.sh en Sistemas Linux

## 1. Archivos Modificados e Incorporados

### Scripts de Infraestructura y Operaciones
- **`scripts/core/restart_services.sh`:**
  - Añadida la función portable:
    ```bash
    run_sed_i() {
        local pattern="$1"
        local file="$2"
        if sed --version >/dev/null 2>&1; then
            sed -i "$pattern" "$file"
        else
            sed -i '' "$pattern" "$file"
        fi
    }
    ```
  - Reemplazadas todas las invocaciones `sed -i ''` (actualización de `GROQ_API_KEY`, `LLM_PROVIDER`, `LLM_MODEL`, `AZURE_MAPS_KEY`) por `run_sed_i`.
- **`scripts/core/install_prod.sh`:**
  - Añadida la misma función `run_sed_i` para reemplazar llamadas directas a `sed -i` en la generación de claves y secretos.

## 2. Validación Cruzada
- **macOS (Darwin / BSD sed):**
  - Probado en bash local: edición in-place exitosa sin errores de argumentos.
- **Linux (GNU sed / Ubuntu):**
  - Probado en contenedor Ubuntu Linux: edición in-place exitosa sin fallo `can't read : No such file or directory`.
- **Verificación sintáctica:**
  - `bash -n scripts/core/restart_services.sh` -> 0 errores.
  - `bash -n scripts/core/install_prod.sh` -> 0 errores.
