# Análisis Técnico: [DASH-SEC-03] Rutas API Duplicadas Fuera del Namespace Versionado /v1

## 1. Causa Raíz (CWE-668)
En `jg_dashboard/routes/api.php:64-89`, un bloque residual duplicaba íntegramente las rutas de la API fuera del prefijo de versión:
```php
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
Route::post('/agents/register', [AgentRegistrationController::class, 'register'])->name('api.agents.register');
Route::post('/agents/heartbeat', [AgentHeartbeatController::class, 'heartbeat'])->middleware('agent.hmac')->name('api.agents.heartbeat');
...
```
Esta duplicación exponía los mismos recursos en dos esferas de URL diferentes (`/api/v1/*` y `/api/*`), violando el principio de diseño de APIs REST versionadas y las directrices de CWE-668 (Exposición de Recursos a la Esfera Incorrecta).

## 2. Implicaciones de Seguridad
1. **Bypass de Reglas Perimetrales:** Filtros de seguridad a nivel de red, rate limiters o reglas de WAF configurados para `/api/v1/*` podían ser esquivados realizando peticiones a `/api/*`.
2. **Superficie de Ataque Innecesaria:** Mantener rutas duales aumenta la complejidad del enrutamiento y el riesgo de desalineación en futuros middlewares o políticas de autorización.
3. **Mantenimiento y Coherencia:** Los clientes front-end y agentes ya consumen `/api/v1/*`; el bloque no versionado constituía deuda técnica no utilizada legítimamente.

## 3. Solución Implementada
1. **Eliminación Total del Bloque No Versionado:** Se eliminaron las líneas 64 a 89 en `routes/api.php`, garantizando que solo exista el grupo `Route::prefix('v1')->group(...)`.
2. **Actualización de Suites de Pruebas:**
   - Se actualizaron 11 archivos de pruebas de integración en `tests/Feature/` para convertir los antiguos tests de alias en comprobaciones de seguridad estrictas que verifiquen que cualquier acceso a rutas sin `/v1` responde con `404 Not Found`.
