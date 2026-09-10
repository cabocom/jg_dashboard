# Findings — jg_dashboard

## DASH-001 — Consola web sin RBAC para acciones de control

- **Proyecto:** jg_dashboard · **Severidad:** HIGH · **Categoría:** SECURITY, AUTHORIZATION
- **Esperado:** crear agentes, ejecutar y aprobar/rechazar requiere rol explícito (admin/operator según acción).
- **Observado / evidencia:** `routes/web.php:14-26` aplica sólo `auth`. `WebConsoleController::storeAgent`, `triggerExecution`, `approveApproval` y `rejectApproval` no comprueban rol. Además, los métodos de aprobación incluyen fallback que crea/resuelve un usuario si falta (`161-172`, `208-219`).
- **Impacto:** cualquier cuenta autenticada, incluido Viewer, puede provisionar, activar acciones y modificar HITL.
- **Reproducción:** iniciar sesión con Viewer y realizar POST a `/console/agents` o `/console/approvals/{id}/approve` con CSRF válido.
- **Recomendación / dependencias:** aplicar middleware/Policy por acción y retirar fallbacks de identidad. No depende del agente.

## DASH-002 — HMAC de AgentHttpClient no proporciona autenticación efectiva

- **Proyecto:** jg_dashboard · **Severidad:** HIGH · **Categoría:** SECURITY, INTEGRATION
- **Esperado:** una firma debe ser comprobada por el agente con timestamp, cuerpo y protección de replay.
- **Observado:** `AgentHttpClient.php:102-121` crea HMAC y envía incluso el secreto plano, pero `jg_agente` no implementa verificador ni middleware.
- **Impacto:** el Dashboard asume una protección inexistente y sigue enviando operaciones sensibles por una frontera que acepta peticiones anónimas.
- **Recomendación:** acordar contrato de autenticación verificable; DASH debe emitir sólo los headers definidos tras ISSUE-AGENT-001. Relación GLOBAL-001.

## DASH-003 — Doble aprobación/rechazo no está serializada

- **Proyecto:** jg_dashboard · **Severidad:** MEDIUM · **Categoría:** BUG, INTEGRATION
- **Evidencia:** `HitlApprovalService.php:225-292` comprueba PENDING, llama remoto y sólo después marca APPROVED; no hay transacción/lock/estado PROCESSING. El rechazo replica el patrón (`351-420`).
- **Impacto / reproducción:** dos operadores concurrentes pueden ambos pasar `isPending()` y emitir dos `Command(resume)` hacia el agente.
- **Recomendación:** transición atómica/locking e idempotency key antes de la llamada remota; reconciliación si el remoto falla.

## DASH-004 — Consola informa activo aunque el heartbeat esté caducado

- **Proyecto:** jg_dashboard · **Severidad:** MEDIUM · **Categoría:** OBSERVABILITY, UX
- **Evidencia:** `WebConsoleController.php:50` usa `status === 'ACTIVE' || isHealthy(90)`, lo que es verdadero para todo ACTIVE incluso sin heartbeat reciente.
- **Impacto:** un agente muerto puede visualizarse sano hasta que el scheduler lo cambie a OFFLINE.
- **Recomendación:** representar salud por timestamp y mostrar transición/latido, no sólo estado almacenado.

## DASH-005 — Componentes DevOps60k heredados siguen activos

- **Proyecto:** jg_dashboard · **Severidad:** MEDIUM · **Categoría:** ARCHITECTURE, TECH_DEBT
- **Evidencia:** `AppServiceProvider` y widgets/Filament usan `DevOps60kApiClient`, que apunta por defecto a `devops60k_app:8000` y al protocolo previo (`/trigger`, `/topology`); el flujo actual usa `AgentHttpClient`.
- **Impacto:** la UI puede consultar un backend inexistente o distinto del agente registrado, mostrando información contradictoria.
- **Recomendación:** retirar o aislar explícitamente la ruta legacy y probar que no participa en consola actual.

## DASH-006 — Defaults de desarrollo inseguros en Compose

- **Proyecto:** jg_dashboard · **Severidad:** LOW · **Categoría:** SECURITY, OPERATIONS
- **Evidencia:** `docker-compose.yml:39-50` fija APP_KEY, `APP_DEBUG=true`, usuario admin y contraseña predecibles por defecto; `config/agent.php:16` define secreto interno conocido.
- **Impacto / recomendación:** riesgo si el archivo se usa fuera de local; fallar el arranque de producción cuando falten secretos y no registrar datos sensibles.
