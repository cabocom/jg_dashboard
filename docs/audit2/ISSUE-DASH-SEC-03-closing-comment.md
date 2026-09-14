# Cierre de Issue: [DASH-SEC-03] Rutas API Duplicadas Fuera del Namespace Versionado /v1

## Resolución
Se ha resuelto satisfactoriamente la exposición de rutas duplicadas no versionadas en la API REST de `jg_dashboard` (Work item [#49](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/49)):

1. **Eliminación del Bloque No Versionado (CWE-668):**
   - En `routes/api.php`, se eliminaron todas las definiciones de endpoints duplicadas que estaban expuestas fuera del prefijo `/api/v1/`.
   - Todos los recursos y acciones de la API REST quedan encapsulados exclusivamente dentro del grupo canónico `Route::prefix('v1')->group(...)`.

2. **Fortalecimiento de la Suite de Pruebas:**
   - Se actualizaron 11 archivos de tests de integración en `tests/Feature/` para asegurar de forma automatizada que cualquier llamada a rutas no versionadas (ej. `/api/agents`, `/api/executions/trigger`, `/api/auth/login`) responda con `404 Not Found`.

3. **Verificación:**
   - La suite completa de `jg_dashboard` superó las 277 pruebas unitarias y de integración (1318 aserciones) con 100% de éxito.
   - El script global de validación `check_unit.sh` finalizó exitosamente (713 tests en el workspace).
