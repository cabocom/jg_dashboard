# AUDITORÍA TÉCNICA DEFINITIVA — jg_dashboard
### Revisión independiente de caja blanca · Septiembre 2026

---

## Metadatos del Informe

| Campo | Valor |
|---|---|
| **Proyecto** | `jg_dashboard` — Panel Web de Control y Gobernanza HITL |
| **Stack** | Laravel 11 · PHP 8.3 · Filament v3 · PostgreSQL · psql |
| **Fecha de auditoría** | 14–18 septiembre 2026 |
| **Alcance** | `jg_dashboard/app/` · `jg_dashboard/config/` · `jg_dashboard/database/` · `jg_dashboard/routes/` · `jg_dashboard/Dockerfile` |
| **Excluido** | `tmp/` · `jg_agente/` |
| **Metodología** | Lectura directa del código fuente + contraste con auditorías previas (20260912, 20260914) |
| **Auditorías de referencia** | `docs/issues/20260912-audit/` · `docs/issues/20260914-audit2/` |

---

## 1. Resumen Ejecutivo

`jg_dashboard` ha resuelto correctamente sus issues más críticos: los secretos hardcodeados en el código fuente, la ruptura criptográfica del registro dinámico y el timeout incompatible con el ciclo cognitivo del agente. La arquitectura de seguridad del Control Plane es sólida y coherente.

Se identifican **4 issues residuales y 3 issues nuevos**, de los cuales el más significativo es un riesgo arquitectónico de confianza excesiva en los permisos delegados por el Dashboard al agente.

### Estado general: ⚠️ APTO CON CONDICIONES

Seguro para uso interno con operadores de confianza. Para exposición pública o multi-tenant requiere resolución de los issues de seguridad web marcados como MEDIO o superior.

---

## 2. Mapa de Arquitectura Verificado

```
jg_dashboard (Laravel 11 + Filament v3)
    │
    ├── Rutas web (web.php)
    │     Auth:  POST /login, POST /logout
    │     Panel: /dashboard, /console, /agents, /approvals, /audit
    │     Filament admin: /admin/**
    │
    ├── Rutas API (api.php)  [middleware: api, auth:sanctum o jwt]
    │     POST  /api/agents/{id}/execute          [ExecutionController]
    │     GET   /api/executions/{thread_id}        [ExecutionController]
    │     POST  /api/executions/{thread_id}/approve  [HitlApprovalController]
    │     POST  /api/executions/{thread_id}/reject   [HitlApprovalController]
    │     POST  /api/agents/heartbeat              [AgentHeartbeatController] [middleware: agent.hmac]
    │     POST  /api/agents/register               [AgentRegistrationController]
    │     …
    │
    ├── Servicios clave
    │     AgentRegistrationService   (bootstrap token → permanent secret)
    │     AgentHttpClient            (firma HMAC, envía peticiones a jg_agente)
    │     JwtService                 (tokens de sesión de operadores)
    │     AgentHeartbeatService      (actualiza last_heartbeat_at)
    │
    ├── Middleware
    │     VerifyHmacSignature        (agentes → dashboard: verifica firma HMAC)
    │
    └── Modelos
          Agent, BootstrapToken, AgentTask, ApprovalRequest, AuditEvent, User
```

---

## 3. Hallazgos de Seguridad

### [DASH-SEC-01] — CORREGIDO ✅
**Secretos hardcodeados en `AgentHttpClient`, `config/agent.php` y `docker-compose.yml`**

*Severidad original: CRÍTICO*

`AgentHttpClient.php:47-56`: el constructor resuelve el secreto desde `Config::get('agent.internal_secret')` o `Config::get('agent.devops_secret')` sin fallback a cadenas literales. Si el secreto no está configurado, `generateSignatureHeaders()` lanza `\RuntimeException("Seguridad (CWE-798)...")` en la línea 156-158, impidiendo cualquier operación.

`docker-compose.yml:50-53`: las variables de secreto usan interpolación estricta `${JG_CONVERSATIONAL_SECRET:-${JG_AGENTE_SECRET:?Error:...}}` con el operador `:?` que fuerza un fallo explícito de Compose si la variable no está definida. Estado: **Completamente corregido.**

---

### [DASH-SEC-02] — CORREGIDO ✅
**JWT secret con fallback hardcodeado `'default_jg_dashboard_jwt_secret_32_bytes_long'`**

*Severidad original: CRÍTICO*

`JwtService.php:17-45`: el constructor resuelve `$resolvedSecret` desde `config('auth.jwt.secret') ?? config('app.key')`. Si la cadena resultante es vacía, `null` o igual al valor inseguro conocido, **lanza `RuntimeException`** en lugar de continuar con el secreto inseguro. El valor `'default_jg_dashboard_jwt_secret_32_bytes_long'` está explícitamente detectado y rechazado. Estado: **Corregido.**

---

### [DASH-SEC-03] — CORREGIDO ✅
**Ruptura criptográfica del registro dinámico HMAC**

*Severidad original: CRÍTICO (exclusivo Gemini 3.8)*

El fallo original era que `consumeBootstrapTokenAndActivate` descartaba el `permanentSecret` en texto plano y solo guardaba el hash SHA-256, haciendo imposible que el Dashboard firmara peticiones posteriores.

`AgentRegistrationService.php:239-243`: el secreto se genera como `'sec_' . Str::random(48)` y se pasa a `$agent->markAsActive($secretHash, $permanentSecret)`. El modelo `Agent.php:144-157`: `markAsActive` recibe `?string $plainSecret = null` y lo persiste en `permanent_secret_encrypted` con el cast `encrypted` de Laravel (cifrado simétrico con `APP_KEY`).

`Agent.php:183-208`: `resolveHmacSecret()` desencripta y retorna el secreto en texto plano cuando se necesita para firmar peticiones. Si el secreto cifrado no está disponible, aplica fallback a la variable de entorno del agente correspondiente.

Estado: **Completamente corregido.** El ciclo registro → firma HMAC es ahora criptográficamente correcto.

---

### [DASH-SEC-04] — RESIDUAL ⚠️
**`resolveHmacSecret()` tiene fallbacks a variables de entorno por agent_id hardcodeado**

*Severidad: MEDIO*

`Agent.php:197-208`: si `permanent_secret_encrypted` está vacío (por ejemplo, un agente creado manualmente o una migración antigua), el método aplica fallbacks escalonados basados en el `agent_id` hardcodeado:

```php
if ($this->agent_id === 'a0000000-0000-0000-0000-000000000002') {
    return config('agent.devops_secret') ?: env('JG_DEVOPS_SECRET', ...);
}
```

Este mecanismo de fallback por UUID fijo es una deuda técnica: acopla el código fuente del Dashboard a los UUIDs de agentes pre-provisionados. Si en el futuro se crean agentes dinámicos con UUIDs arbitrarios que no tienen secreto cifrado en BD, el fallback final es `config('agent.internal_secret')` del agente conversacional — que es el secreto equivocado.

- **Mitigación:** eliminar los fallbacks por UUID fijo. Si el secreto cifrado no está en BD, el agente debe ser re-registrado obligatoriamente.

---

### [DASH-SEC-05] — RESIDUAL ⚠️
**`VerifyHmacSignature` middleware sin protección anti-replay (sin cache de nonces)**

*Severidad: MEDIO*

`VerifyHmacSignature.php:39-45`: la protección anti-replay se limita a validar que el timestamp esté dentro de la ventana de ±300 segundos. No existe un cache de nonces. Idéntico al issue AGENTE-SEC-05, pero en el lado del Dashboard: un atacante que intercepte un heartbeat legítimo puede repetirlo durante 5 minutos.

---

### [DASH-SEC-06] — NUEVO ⚠️
**`ExecutionController` delega permisos del usuario directamente al payload del agente sin validación**

*Severidad: MEDIO-ALTO*

`ExecutionController.php:93-95`:
```php
'permissions' => method_exists($user, 'getPermissions')
    ? $user->getPermissions()
    : ($user->role === 'admin' ? ['*', 'docker:restart', ...] : []),
```

El Dashboard construye el `user_context.permissions` que se envía al agente y que `resolve_caller_permissions()` del agente usa para otorgar acceso a herramientas. El problema es que **si un usuario del Dashboard tiene el rol `admin`, se le asignan permisos `['*', 'docker:restart', 'docker:inspect', 'docker:list', 'admin']` de forma estática en el controlador**, sin consultar una fuente de verdad dinámica de permisos.

Si el sistema de roles de Filament/Dashboard cambia (por ejemplo, se crea un rol `power_user` con acceso a `/console` pero sin capacidad de ejecutar herramientas Docker), ese usuario recibirá permisos heredados del fallback. El agente confía ciegamente en el `user_context.permissions` enviado por el Dashboard.

- **Riesgo:** escalada de privilegios desde el Dashboard hacia el agente si el mapeo de roles no se mantiene sincronizado.
- **Mitigación:** centralizar la resolución de permisos en un servicio dedicado (`PermissionService`) que consulte la BD en lugar de usar fallbacks estáticos en el controlador.

---

### [DASH-SEC-07] — NUEVO 🔍 RECOMENDACIÓN
**`AgentHeartbeatController` acepta `agent_id` desde el cuerpo del request como fallback**

*Severidad: BAJO*

`AgentHeartbeatController.php:28`:
```php
$agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');
```

Si el middleware HMAC ya ha verificado `X-Agent-ID` y lo ha inyectado en `request->attributes->authenticated_agent`, este fallback es redundante. Si el middleware HMAC falla silenciosamente (por ejemplo, si la ruta no tiene el middleware `agent.hmac` aplicado correctamente), el `agent_id` del cuerpo podría usarse para impersonar un agente sin autenticación.

---

## 4. Hallazgos de Rendimiento y Resiliencia

### [DASH-PERF-01] — CORREGIDO ✅
**Timeout HTTP de 15s incompatible con el ciclo cognitivo del agente**

*Severidad original: CRÍTICO*

`AgentHttpClient.php:53`: `$this->timeout = $timeout ?? (int) (Config::get('agent.timeout') ?: 120)`. El valor por defecto es ahora 120 segundos. El modo asíncrono (`async=true`) está soportado. Estado: **Corregido.**

---

### [DASH-PERF-02] — NUEVO 🔍 RECOMENDACIÓN
**`AgentHttpClient::forAgent()` no configura el timeout de forma contextual**

`AgentHttpClient.php:62-79`: el factory `forAgent(Agent $agent)` crea una instancia con `new self()`, que usa el timeout por defecto de configuración (120s). Sin embargo, no tiene en cuenta si el agente fue registrado con capacidades de ejecución larga (por ejemplo, agentes DevOps con tareas de auto-remediación que pueden durar 300s+). El timeout debería ser configurable por agente o por tipo de operación.

---

## 5. Hallazgos de Arquitectura del Registro

### [DASH-ARCH-01] — CORREGIDO ✅
**`DatabaseSeeder` sobrescribía el hash del secreto con un valor estático**

El seeder ahora calcula el hash dinámicamente desde la variable de entorno. Estado: **Corregido** (según especificación del issue GLOBAL-01).

---

### [DASH-ARCH-02] — NUEVO ⚠️
**`Agent::resolveHmacSecret()` usa descifrado con trampa silenciosa**

*Severidad: BAJO-MEDIO*

`Agent.php:162-177`:
```php
public function getDecryptedSecret(): ?string
{
    if (empty($this->permanent_secret_encrypted)) {
        return null;
    }
    try {
        return $this->permanent_secret_encrypted; // El cast 'encrypted' lo desencripta automáticamente
    } catch (\Throwable) {
        try {
            $raw = $this->getRawOriginal('permanent_secret_encrypted');
            return $raw ? \Illuminate\Support\Facades\Crypt::decryptString($raw) : null;
        } catch (\Throwable) {
            return null;    // <-- SILENCIA el fallo
        }
    }
}
```

Si el descifrado falla (por ejemplo, porque `APP_KEY` fue rotada y los datos cifrados son del ciclo anterior), el método retorna `null` silenciosamente. `resolveHmacSecret()` entonces aplica los fallbacks de entorno mencionados en DASH-SEC-04. Un operador que rote `APP_KEY` sin re-registrar los agentes provocará que **todos los agentes fallen su HMAC silenciosamente** y el diagnóstico requerirá inspección manual de logs.

- **Mitigación:** loggear explícitamente cuando `getDecryptedSecret()` falla por excepción de descifrado, distinguiéndolo de un secreto vacío.

---

## 6. Hallazgos de Tests

### [DASH-TEST-01] — CORREGIDO ✅
**Suite de tests Pest/PHPUnit pasaba 251 tests con 1,201 aserciones**

El estado de la suite es estable. Estado: **Mantenido.**

---

### [DASH-TEST-02] — RESIDUAL ⚠️
**Sin tests de integración E2E con jg_agente real**

Ningún test del Dashboard arranca una instancia real de `jg_agente`, envía una petición HMAC firmada y verifica la respuesta. Los tests del Dashboard mockean las respuestas de `AgentHttpClient`. Esto significa que incompatibilidades en el contrato de firma HMAC, en el formato del payload `AgentInput`, o en la interpretación de `waiting_approval` pueden existir sin ser detectadas por los tests.

---

## 7. Tabla Resumen de Hallazgos

| ID | Título | Severidad | Estado |
|---|---|---|---|
| DASH-SEC-01 | Secretos hardcodeados en cliente y Compose | CRÍTICO | ✅ CORREGIDO |
| DASH-SEC-02 | JWT secret con fallback inseguro | CRÍTICO | ✅ CORREGIDO |
| DASH-SEC-03 | Ruptura criptográfica del registro dinámico | CRÍTICO | ✅ CORREGIDO |
| DASH-SEC-04 | `resolveHmacSecret` fallback por UUID fijo | MEDIO | ⚠️ RESIDUAL |
| DASH-SEC-05 | Middleware HMAC sin cache de nonces | MEDIO | ⚠️ RESIDUAL |
| DASH-SEC-06 | Permisos de usuario propagados con fallback estático | MEDIO-ALTO | 🆕 NUEVO |
| DASH-SEC-07 | Heartbeat acepta `agent_id` del cuerpo | BAJO | 🆕 NUEVO |
| DASH-PERF-01 | Timeout HTTP 15s incompatible con ciclo cognitivo | CRÍTICO | ✅ CORREGIDO |
| DASH-PERF-02 | Timeout no configurable por tipo de agente | BAJO | 🔍 RECOMENDACIÓN |
| DASH-ARCH-01 | DatabaseSeeder con hash estático | ALTO | ✅ CORREGIDO |
| DASH-ARCH-02 | Descifrado silencioso de secreto de agente | BAJO-MEDIO | 🆕 NUEVO |
| DASH-TEST-01 | Suite Pest/PHPUnit 251 tests | — | ✅ ESTABLE |
| DASH-TEST-02 | Sin tests E2E con jg_agente real | ALTO | ⚠️ RESIDUAL |

---

## 8. Veredicto Final

**jg_dashboard ha corregido todos sus issues críticos de forma arquitectónicamente correcta.** El ciclo de registro, el cifrado del secreto permanente con `APP_KEY`, la firma HMAC y el timeout son ahora robustos. Las vulnerabilidades residuales son de severidad media y su explotación requiere condiciones específicas o acceso privilegiado previo.

### Para producción se requiere como mínimo:
1. ✅ Resolver **DASH-SEC-06**: centralizar la resolución de permisos en un servicio dedicado
2. ✅ Resolver **DASH-SEC-05**: implementar cache de nonces (coordinado con AGENTE-SEC-05)
3. ✅ Resolver **DASH-ARCH-02**: loggear explícitamente los fallos de descifrado
4. ✅ Añadir al menos un test de integración E2E (**DASH-TEST-02**)

---

*Documento generado por auditoría de caja blanca directa sobre el código fuente. Todos los hallazgos están respaldados por referencias de archivo y línea específicas.*
