# Contexto del Proyecto: [DASH-SEC-03] Rutas API Duplicadas Fuera del Namespace Versionado /v1

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#49`
- **Código:** `DASH-SEC-03`
- **Severidad:** 🟡 MEDIO
- **CWE:** CWE-668 (Exposure of Resource to Wrong Sphere)
- **Componentes Afectados:**
  - [`jg_dashboard/routes/api.php`](../../../routes/api.php)
  - [`jg_dashboard/tests/Feature/AgentHeartbeatApiTest.php`](../../../tests/Feature/AgentHeartbeatApiTest.php)
  - [`jg_dashboard/tests/Feature/HitlApprovalApproveApiTest.php`](../../../tests/Feature/HitlApprovalApproveApiTest.php)
  - [`jg_dashboard/tests/Feature/HitlApprovalRejectApiTest.php`](../../../tests/Feature/HitlApprovalRejectApiTest.php)
  - [`jg_dashboard/tests/Feature/HitlApprovalsPendingApiTest.php`](../../../tests/Feature/HitlApprovalsPendingApiTest.php)
  - [`jg_dashboard/tests/Feature/InternalExecutionWebhookTest.php`](../../../tests/Feature/InternalExecutionWebhookTest.php)
  - [`jg_dashboard/tests/Feature/ExecutionStatusApiTest.php`](../../../tests/Feature/ExecutionStatusApiTest.php)
  - [`jg_dashboard/tests/Feature/ExecutionTriggerApiTest.php`](../../../tests/Feature/ExecutionTriggerApiTest.php)
  - [`jg_dashboard/tests/Feature/AgentCapabilitiesApiTest.php`](../../../tests/Feature/AgentCapabilitiesApiTest.php)
  - [`jg_dashboard/tests/Feature/AgentRegistrationApiTest.php`](../../../tests/Feature/AgentRegistrationApiTest.php)
  - [`jg_dashboard/tests/Feature/AgentListApiTest.php`](../../../tests/Feature/AgentListApiTest.php)
  - [`jg_dashboard/tests/Feature/AuthApiTest.php`](../../../tests/Feature/AuthApiTest.php)

---

## Descripción de la Vulnerabilidad
En `routes/api.php`, todos los endpoints de la API REST del Control Plane estaban declarados dos veces:
1. Dentro del grupo con prefijo `v1` (`Route::prefix('v1')->group(...)`), exponiendo rutas bajo `/api/v1/...`.
2. Como bloque secundario fuera de dicho grupo, exponiendo los mismos controladores y métodos directamente bajo la raíz `/api/...` (ej. `/api/auth/login`, `/api/agents`, `/api/executions/trigger`, `/api/approvals/pending`).

## Impacto en Seguridad
La exposición de rutas redundantes no versionadas incrementa innecesariamente la superficie de ataque y permite la evasión de políticas de seguridad implementadas en proxies inversos, balanceadores o Web Application Firewalls (WAF) que apliquen inspecciones o límites de tráfico (rate limiting) exclusivamente sobre el patrón canónico `/api/v1/*`.
