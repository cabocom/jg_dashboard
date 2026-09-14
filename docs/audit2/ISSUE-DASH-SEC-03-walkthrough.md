# Walkthrough: [DASH-SEC-03] Rutas API Duplicadas Fuera del Namespace Versionado /v1

## Resumen de Cambios

### 1. `jg_dashboard/routes/api.php`
- Se eliminaron las 26 líneas de rutas duplicadas no versionadas (líneas 64 a 89).
- Todos los endpoints quedan centralizados bajo el prefijo canónico `v1` (`Route::prefix('v1')->group(...)`).

### 2. Actualización de Tests de Feature en `jg_dashboard/tests/Feature/`
- Se reconvirtieron las pruebas de alias en validaciones de seguridad CWE-668 que comprueban que las rutas no versionadas devuelven `404 Not Found`:
  - `AgentHeartbeatApiTest.php`
  - `HitlApprovalApproveApiTest.php`
  - `HitlApprovalRejectApiTest.php`
  - `HitlApprovalsPendingApiTest.php`
  - `InternalExecutionWebhookTest.php`
  - `ExecutionStatusApiTest.php`
  - `ExecutionTriggerApiTest.php`
  - `AgentCapabilitiesApiTest.php`
  - `AgentRegistrationApiTest.php`
  - `AgentListApiTest.php`
  - `AuthApiTest.php`

---

## Verificación de Pruebas

```bash
# Suite de pruebas Pest en jg_dashboard
cd jg_dashboard && ./vendor/bin/pest
# 277 passed (1318 assertions)

# Verificación global del workspace
bash check_unit.sh
# 713 tests pasados exitosamente
```
