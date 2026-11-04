# CONTEXTO DE PROYECTO: jg_dashboard

**Versión del documento:** 3.35.0  
**Fecha de consolidación:** 26 de octubre de 2026  
**Componente:** Control Plane (Plano de Control, Gestión, Gobernanza y HITL)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, Tailwind CSS, PostgreSQL, Pest PHP  
**Estado:** Fases 01 a 08 y Fase 09 completadas al 100%. Soporte multi-agente, pasarela completa de adaptadores externos (Webhooks, CI/CD, Chatbots y Asterisk Telephony Adapter con soporte bidireccional 'response' y 'tts_text'), servicio criptográfico JWT estándar con rotación y blacklist, webhook de actualización asíncrona HMAC, paginación homogénea de 50 elementos por defecto, y compatibilidad multiplataforma Linux/macOS en scripts operativos.  
- Compatibilidad de Telefonía Asterisk (DASH-AST-02): Incorporación de la clave canónica `'response'` junto a `'tts_text'` en el payload generado por `MockAsteriskChatAdapter` para asegurar la integración transparente e interoperable con gateways de voz ARI/Asterisk.
- Paginación Homogénea de 50 Elementos por Defecto (DASH-PAG-01): Configuración transversal de 50 elementos por página en todas las tablas y recursos administrativos de Filament mediante `Table::configureUsing(...)` en `AppServiceProvider::boot()`, y actualización del valor por defecto en los endpoints de listado de `AgentController@index` (`$perPage = 50`) y `HitlApprovalService@getPendingApprovals` (`$perPage = 50`).
- Adaptador de Telefonía Asterisk y Trazabilidad de Llamadas (DASH-AST-01): Normalización del caller ID en `MockAsteriskChatAdapter`, asociando correctamente el número del llamante como identidad de usuario (`user_context.username` y `metadata.caller_id`).
- Resiliencia en Visor de Trazas (`ExecutionController@show` y `AgentTask`): Capacidad de servir la traza y saltos cognitivos directamente desde la persistencia local de `AgentTask` para tareas concluidas o adapters asíncronos/telefónicos, evitando dependencias forzadas con el upstream una vez completada la ejecución. Fallbacks automáticos en `AgentTask` para `user_name` y `agent_id`.
- Actualización de Identidad de Administrador por Defecto (DASH-AUTH-01): Migración del dominio de aprovisionamiento administrativo inicial de `devops60k.local` a `javiergalvez.test.local` (`admin@javiergalvez.test.local`) en el comando CLI `dashboard:init-admin`, `DatabaseSeeder`, `docker-compose.yml` y tests unitarios de persistencia.  
- Compatibilidad Multiplataforma Linux/macOS en Scripts (DASH-OPS-01 / GitLab jg_dashboard#55): Implementada función auxiliar portable `run_sed_i` en `scripts/core/restart_services.sh` e `install_prod.sh`, solventando la incompatibilidad entre la sintaxis de edición in-place BSD sed (`macOS: sed -i ''`) y GNU sed (`Linux: sed -i`) para ejecuciones fiables en entornos CI/CD y servidores Linux.  
- Webhooks de Actualización Asíncrona HMAC (DASH-CODE-02 / GitLab jg_dashboard#53): Implementado endpoint `POST /api/v1/internal/executions/{execution_id}/status-update` autenticado mediante `agent.hmac` (firmas HMAC-SHA256 con protección anti-replay 300s); permite a `jg_agente` notificar en tiempo real transiciones de estado, pasos cognitivos, pausas HITL y finalizaciones, superando la dependencia exclusiva de polling continuo. `ExecutionController@trigger` inyecta automáticamente `status_callback_url` y `callback_url` en modo asíncrono (`async=true`).  
- Criptografía JWT Estándar, Rotación de Claves y Blacklist (DASH-CODE-01 / GitLab jg_dashboard#52): Sustitución de la codificación y firma manual de JWT por la biblioteca estándar de la industria `firebase/php-jwt` (^7.2.1); incorporación de soporte de rotación de claves (`kid`) vía `config/auth.php` y `JwtService`; generación de identificadores únicos por token (`jti` UUIDv4); e implementación de blacklist/revocación en doble nivel (Caché L1 en memoria y tabla PostgreSQL `dashboard_jwt_revocations` L2) con invalidación inmediata al invocar `POST /api/v1/auth/logout`.  
- Fase 08 (Adaptadores de Estímulos Externos / ISSUE-F08-T01, ISSUE-F08-T02 & ISSUE-F08-T03): Implementada la capa completa de adaptadores `App\Adapters` con `InputNormalizer` (transformación multi-fuente a `AgentInput`), `ClientOutputFormatter` (formateo adaptativo `generic`, `chatbot`, `ci_cd`), orquestador `HttpWebhookAdapter` (endpoints `POST /api/v1/adapters/webhook` y `POST /api/v1/adapters/webhook/{agent_id}`) y adaptador simulado conversacional `MockAsteriskChatAdapter` (`POST /api/v1/adapters/mock-asterisk` y `POST /api/v1/adapters/mock-conversational`) para pruebas de telefonía/voz Asterisk PBX y chat sin requerir PBX real.  
- Soporte Multi-Agente: Orquestación y enrutamiento simultáneo de múltiples runtimes (`jg_agente_conversacional`:8001, `jg_agente_devops`:8002 y `jg_agente_datosgob`:8881) mediante `AgentHttpClient` con firma canónica HMAC-SHA256 (`METHOD\nPATH\nTIMESTAMP\nSHA256(BODY)`).  
- Post-Auditoría 2 (GLOBAL-01 / GitLab jg_dashboard#42): Erradicación total de secretos fallback hardcodeados en `AgentHttpClient.php`, `config/agent.php` y `docker-compose.yml`; interpolación obligatoria `${JG_CONVERSATIONAL_SECRET:?err}` y `${JG_DEVOPS_SECRET:?err}`; derivación dinámica de `permanent_secret_hash` en `DatabaseSeeder.php` desde configuración activa con validación previa estricta.  
- Post-Auditoría 2 (GLOBAL-02 / GitLab jg_dashboard#43): Corrección de la ruptura criptográfica del registro dinámico de agentes. Incorporación de columna cifrada en reposo `permanent_secret_encrypted` (AES-256-CBC respaldada por `APP_KEY`) en `dashboard_agents`, persistida durante `AgentRegistrationService::consumeBootstrapTokenAndActivate(...)` y consumida prioritariamente por `AgentHttpClient::forAgent(...)` para el cálculo determinista de firmas HMAC `X-Signature`.  
- Post-Auditoría 2 (GLOBAL-03 / GitLab jg_dashboard#44): Elevación del timeout HTTP por defecto a 120 segundos (`AGENT_HTTP_TIMEOUT=120`) en `config/agent.php` y `AgentHttpClient.php` para acomodar el ciclo cognitivo completo de 9-10 nodos sin cortes prematuros 504 Gateway Timeout. Incorporación de soporte para ejecución asíncrona (`async=true`) en `/console` con polling reactivo cada 3 segundos, y persistencia garantizada de tareas fallidas (`FAILED`) en `AgentTask` ante errores o timeouts de pasarela.  
- Post-Auditoría 2 (GLOBAL-05 / GitLab jg_dashboard#46): Propagación de cabecera `X-Correlation-ID` e integración de trazabilidad distribuida e2e; `AgentHttpClient` preserva y expone el identificador de correlación vía `getCorrelationId()`, `ExecutionController` captura el ID entrante o genera UUIDv4 canónico inyectándolo en `AgentInput`, en los metadatos y en `AuditEvent::record(...)` dentro de `payload_json['correlation_id']`, retornándolo uniformemente en encabezados HTTP y respuestas JSON.  
- Post-Auditoría Codex (GLOBAL-001 / ISSUE-DASH-002): Eliminación de la cabecera en texto plano `X-Control-Plane-Secret` y alineación estricta con el contrato de firma canónica y anti-replay del agente.  
- Post-Auditoría Codex (GLOBAL-003 / ISSUE-DASH-003): Transaccionalidad, bloqueo pesimista `lockForUpdate` y control de concurrencia en HITL con estado `PROCESSING` y reconciliación automática ante fallos de red.  
- Gobernanza HITL Transparente con Desglose de Etapas Mutativas en Rojo: Visualización en `/console` (modales de aprobación e inspección) del desglose completo del plan de acción planificado por el agente. Inclusión de banner de advertencia gobernada si hay etapas mutativas, resaltando en rojo (`step-mutative-badge`) las etapas que alteran el entorno y en verde (`step-readonly-badge`) las de solo lectura, impidiendo que el operador apruebe mutaciones a ciegas.
- Concurrencia HITL Pesimista Reforzada (`lockForUpdate` / `SELECT ... FOR UPDATE`): `HitlApprovalService` previene condiciones de carrera concurrentes garantizando que solo una aprobación simultánea gane el bloqueo pesimista (HTTP 200) mientras las demás reciben HTTP 409 Conflict inmediato con reconciliación a `PENDING` si la ejecución aguas arriba fallase.
- Prevención Universal de Doble Clic en Formularios y Botones de la Consola.
- Consola Web Unificada: `/console` operativa con panel reactivo de agentes, métricas de salud en tiempo real, gestión de aprobaciones HITL con visualización de planes de remediación y visor de ejecuciones en vivo.  
- Aislamiento Total de Persistencia: PostgreSQL propio `jg_dashboard_postgres` (puerto 5434), sin acceso directo a la base de datos de los agentes (`jg_agente_postgres`:5433).  
- Suite de Pruebas: Suite de pruebas completa pasando al 100% (336 tests Pest / PHPUnit con 1560 assertions en 5.9s).

---

## 1. Propósito del Proyecto

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto externamente** hacia usuarios administradores, operadores y clientes/sistemas externos.

Actúa como el orquestador central que gestiona el ciclo de vida de los agentes (`jg_agente`), administra el control de accesos basado en roles (RBAC), proporciona la consola de aprobación humana (HITL - Human-In-The-Loop) para operaciones de riesgo y aloja o media con los adaptadores de protocolos externos (REST, Web, interfaces conversacionales).

---

## 2. Principios Arquitectónicos Fundamentales

1. **Único Punto de Exposición Externa:**
   `jg_dashboard` es el frontispicio de seguridad del ecosistema. Todos los agentes `jg_agente` residen en redes internas privadas o entornos aislados sin acceso directo desde Internet. Cualquier interacción humana o externa pasa obligatoriamente por el Dashboard.

2. **Aislamiento Total de Persistencia (No Direct DB Access):**
   Queda terminantemente prohibido que `jg_dashboard` acceda directamente a la base de datos de ningún agente.
   ```text
   CORRECTO:   jg_dashboard ──[HTTP REST API / Red Interna]──► jg_agente
   PROHIBIDO:  jg_dashboard ──► [PostgreSQL de jg_agente]
   ```
   El Dashboard mantiene su propia base de datos PostgreSQL independiente para gestionar usuarios, agentes registrados, tokens de aprovisionamiento, historial de aprobaciones y auditoría del plano de control.

3. **Gobernanza Human-In-The-Loop (HITL):**
   Cuando un agente pausa su ejecución tras detectar un plan con nivel de riesgo crítico (`RiskLevel >= HIGH`), el Dashboard recibe la alerta, presenta el plan de ejecución y los comandos de compensación al operador humano, y transmite la decisión (`approve` o `reject`) al agente para reanudar el grafo.

4. **Desacoplamiento de Protocolos y Normalización:**
   Las fuentes externas (telefonía, mensajería, webhooks, portales) se conectan al Dashboard. El Dashboard o sus módulos adaptadores transforman los estímulos en un payload estándar `AgentInput` y traducen la respuesta `AgentOutput` al canal emisor. El agente nunca conoce la tecnología del cliente final.

5. **Aprovisionamiento Criptográfico de Agentes (Zero-Trust Bootstrap):**
   Los agentes se registran de forma segura mediante tokens de un solo uso con caducidad estricta (TTL 24h), sustituyéndose inmediatamente por secretos permanentes gestionados y revocables desde el Dashboard.

6. **Pasarela y Proxy Inteligente de I/O para Entornos Heterogéneos (`App\Adapters`):**
   `jg_dashboard` opera como un proxy inteligente que absorbe la heterogeneidad de clientes e infraestructuras externas (disparadores CI/CD de GitLab/GitHub, telefonía Asterisk PBX, bots de mensajería y REST heredado). La capa de adaptadores:
   - Ingiere y normaliza estímulos arbitrarios hacia `AgentInput` tipado mediante `InputNormalizer`.
   - Gestiona la correlación (`correlation_id`, `execution_id`, `thread_id`) y auditoría.
   - Enruta y firma criptográficamente con HMAC-SHA256 las llamadas internas hacia `jg_agente`.
   - Aplica gobernanza y pausa HITL transparente si una herramienta crítica requiere aprobación humana.
   - Serializa y formatea la respuesta `AgentOutput` hacia el dialecto nativo del consumidor con `ClientOutputFormatter` (o adaptadores especializados como `MockAsteriskChatAdapter`).

---

## 3. Matriz de Responsabilidades

| Responsabilidad | Dashboard (`jg_dashboard`) | Agente (`jg_agente`) |
| :--- | :--- | :--- |
| **Exposición a Internet / SSL Termination** | **Exclusiva** | Prohibida (red interna aislada) |
| **Autenticación y Roles de Usuario (RBAC)** | **Exclusiva** (Admin, Operator, Viewer) | Ninguna |
| **Generación de Bootstrap Tokens (TTL 24h)** | **Exclusiva** | Ninguna (solo consume) |
| **Emisión y Revocación de Credenciales Permanentes** | **Exclusiva** | Almacena su propio secreto |
| **Monitorización de Salud y Heartbeats** | Agrega y monitoriza | Emite periódicamente (`/heartbeat`) |
| **Consola de Aprobación Humana (HITL)** | **Exclusiva** (UI/API de aprobación) | Pausa/reanuda checkpoints |
| **Traducción de Protocolos Externos** | Adapta hacia `AgentInput` | Consume exclusivamente `AgentInput` |
| **Ejecución del Grafo Cognitivo** | Ninguna (solo dispara `/trigger`) | **Exclusiva** (LangGraph) |
| **Persistencia de Checkpoints y Tools** | Ninguna | **Exclusiva** (`core_checkpoints`) |

---

## 4. Flujo de Registro y Gestión del Ciclo de Vida del Agente

El registro de nuevos agentes sigue un protocolo de cuatro pasos a prueba de accesos no autorizados:

```text
1. Generación de Invitación (Dashboard UI):
   - El Administrador pulsa "Registrar nuevo agente" en la interfaz.
   - Introduce el nombre del agente (ej: "Agente-DevOps-Cluster-A").
   - El Dashboard genera:
     * agent_id: UUIDv4
     * bootstrap_token: Token aleatorio criptográfico (TTL = 24 horas, uso único)
   - El Dashboard guarda el registro en estado PENDING_REGISTRATION.

2. Aprovisionamiento e Inicio del Agente:
   - El operador o sistema de despliegue arranca el contenedor jg_agente inyectando:
     * DASHBOARD_URL=http://jg_dashboard:8000
     * AGENT_BOOTSTRAP_TOKEN=bt_xxxxxxxxxxxx

3. Intercambio de Credencial en Boot:
   - jg_agente emite petición HTTP:
     POST /api/v1/agents/register
     Headers: Authorization: Bearer <bootstrap_token>
     Body: { "agent_name": "...", "version": "1.0.0", "capabilities": [...] }
   - El Dashboard valida:
     * ¿Existe el token y no ha expirado (< 24h)?
     * ¿Está en estado PENDING_REGISTRATION?
   - Si es válido:
     * Marca el bootstrap_token como CONSUMED (invalida su reutilización).
     * Genera permanent_agent_secret (hash guardado en BD del Dashboard).
     * Cambia el estado del agente a ACTIVE.
     * Responde con: { "agent_id": "...", "permanent_secret": "sec_xxxxxxxx" }

4. Operación Regular y Heartbeats:
   - jg_agente guarda su permanent_secret.
   - En reinicios futuros, jg_agente no necesita bootstrap_token.
   - Cada N segundos (configurable, ej. 30s), jg_agente emite:
     POST /api/v1/agents/heartbeat
     Headers: X-Agent-ID: <id>, Authorization: Bearer <permanent_secret>
     Body: { "status": "healthy", "metrics": { "cpu": 12.5, "mem_mb": 256 }, "active_executions": 0 }
   - Si transcurren > 90 segundos sin heartbeat, el Dashboard marca el agente como UNHEALTHY/OFFLINE.
```

---

## 5. Arquitectura de la API de Comunicación

### 5.1 Endpoints Expuestos por `jg_dashboard`
- `POST /api/v1/auth/login`: Autenticación JWT para administradores, operadores y observadores.
- `GET /api/v1/auth/me`: Perfil de usuario, roles y permisos efectivos.
- `POST /api/v1/auth/logout`: Revocación inmediata del token Bearer de sesión e invalidación en lista negra (Cache + DB).
- `POST /api/v1/agents/register`: Validación del bootstrap token y emisión de credenciales permanentes para agentes.
- `POST /api/v1/agents/heartbeat`: Recepción de estado de salud y telemetría de agentes activos.
- `GET /api/v1/agents`: Listado paginado con métricas agregadas y filtros por estado.
- `GET /api/v1/agents/{agent_id}`: Consulta detallada de estado de un agente específico.
- `GET /api/v1/agents/{agent_id}/capabilities`: Inspección en vivo del catálogo de herramientas, engines cognitivos y plugins del agente con sincronización local.
- `POST /api/v1/executions/trigger`: Pasarela protegida (roles `admin` y `operator`) para disparar ejecuciones hacia `jg_agente` con normalización a `AgentInput`, captura y registro automático de solicitudes HITL vía `HitlApprovalService`.
- `GET /api/v1/executions/{agent_id}/{thread_id}`: Consulta unificada de progreso, pasos y estado de ejecución hacia la API de `jg_agente` con estricto aislamiento de base de datos.
- `POST /api/v1/internal/executions/{execution_id}/status-update`: Webhook interno autenticado con HMAC (`agent.hmac`) para que los agentes notifiquen en tiempo real transiciones de estado, pasos cognitivos, pausas HITL y finalizaciones en tareas asíncronas.
- `POST /api/v1/internal/executions/{execution_id}/complete`: Webhook de finalización asíncrona de ejecuciones.
- `GET /api/v1/approvals/pending`: Listado paginado de solicitudes HITL pendientes para operadores y administradores, con resumen de acciones, nivel de riesgo, herramientas involucradas y métricas de tiempo transcurrido.
- `POST /api/v1/approvals/{approval_id}/approve`: Endpoint de resolución HITL para operadores y administradores con bloqueo pesimista `lockForUpdate` en `HitlApprovalService`, transición inmediata a `PROCESSING`, delegación `/executions/{thread_id}/approve` hacia `jg_agente` mediante `AgentHttpClient`, reconciliación a `PENDING` si la llamada de red falla, o transición definitiva a `APPROVED` con auditoría inmutable tras éxito.
- `POST /api/v1/approvals/{approval_id}/reject`: Endpoint de cancelación HITL para operadores y administradores con justificación obligatoria, bloqueo pesimista `lockForUpdate`, transición a `PROCESSING`, delegación `/executions/{thread_id}/reject` hacia `jg_agente`, reconciliación a `PENDING` ante fallos de red, o transición a `REJECTED` con auditoría inmutable tras confirmación.

### 5.2 Endpoints Invocados por `jg_dashboard` hacia `jg_agente` (Proxy Interno `AgentHttpClient`)
El cliente `App\Services\Agents\AgentHttpClient` canaliza la comunicación firmada mediante HMAC-SHA256 con las instancias internas de `jg_agente`:
- `GET /health` (o `GET /`): Verificación de disponibilidad y latencia de subsistemas.
- `GET /capabilities`: Inspección de herramientas y plugins activos del agente.
- `POST /executions/trigger`: Envía un payload formal `AgentInput` para disparar una ejecución cognitiva.
- `GET /executions/{thread_id}`: Consulta estado detallado y traza de pasos de una ejecución.
- `POST /executions/{thread_id}/approve`: Reanuda una ejecución detenida en el checkpoint HITL.
- `POST /executions/{thread_id}/reject`: Cancela una ejecución detenida en HITL.
- `GET /metrics`: Inspección de métricas de telemetría y rendimiento.

> [!NOTE]
> **Contrato Criptográfico Canónico:**
> `canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)`  
> Las cabeceras emitidas son `X-Signature`, `X-Timestamp` y `X-Agent-ID`. La cabecera en texto plano `X-Control-Plane-Secret` ha sido completamente retirada de las peticiones para prevenir fugas de secretos.

---

## 6. Persistencia de Datos del Dashboard

El Dashboard cuenta con su **propia base de datos PostgreSQL**, completamente desacoplada de los agentes:
- `dashboard_users`: Usuarios administradores, operadores y credenciales con hash Argon2/Bcrypt.
- `dashboard_roles` y `dashboard_permissions`: Definición de perfiles RBAC (`ADMIN`, `OPERATOR`, `VIEWER`).
- `dashboard_agents`: Registro de agentes, nombres, estado (`PENDING`, `ACTIVE`, `OFFLINE`), hash de verificación (`permanent_secret_hash`), credencial permanente cifrada en reposo (`permanent_secret_encrypted`) y metadatos.
- `dashboard_bootstrap_tokens`: Registro de tokens de aprovisionamiento con timestamp de creación, expiración (24h) y estado de uso.
- `dashboard_approval_requests`: Registro histórico de planes pendientes de aprobación humana, usuario aprobador/rechazador, timestamp y justificación.
- `dashboard_audit_events`: Registro de auditoría de todas las acciones administrativas realizadas en el Dashboard.

---

## 7. Módulo de Adaptadores Normalizadores (I/O Gateway)

Para atender a la directriz arquitectónica que prohíbe interfaces externas en el agente, `jg_dashboard` proporciona la capa modular de adaptadores `App\Adapters`:
1. **Normalizador Canónico (`App\Adapters\Normalizers\InputNormalizer`):**
   - Implementa `InputNormalizerInterface`.
   - Ingiere cargas heterogéneas (REST genérico, Webhooks de CI/CD como GitLab/GitHub, Chatbots como Slack/Telegram).
   - Genera o preserva de forma consistente `execution_id` (UUIDv4), `thread_id` (preservando identificadores de sesión o canal como `chat_id` o `pipeline_id`) y `correlation_id` (propagando `X-Correlation-ID`).
   - Construye el contrato canónico formal `AgentInput` con contexto de usuario externo y metadatos de procedencia.
2. **Formateador de Salida Adaptativo (`App\Adapters\Formatters\ClientOutputFormatter`):**
   - Implementa `OutputFormatterInterface`.
   - Traduce `AgentOutput` al formato nativo del consumidor:
     - `generic`: Respuesta JSON exhaustiva con métricas, estado y datos de grafo.
     - `chatbot`: Estructura conversacional limpia (`response`, `text`, `thread_id`).
     - `ci_cd`: Estructura orientada a pipelines (`status`, `job_status`, `summary`, `pipeline_status`).
3. **Orquestador Webhook (`App\Adapters\HttpWebhookAdapter`):**
   - Valida autenticación por secreto (`X-Webhook-Secret`, token Bearer o query param).
   - Resuelve el agente dinámicamente (`agent_id` en ruta, en payload o primer agente activo en la base de datos).
   - Registra auditoría inmutable (`adapter.webhook.received`, `adapter.webhook.completed`) y persiste `AgentTask`.
   - Soporta ejecuciones síncronas (200 OK) y asíncronas con encolado (202 Accepted).
4. **Adaptador Simulado Conversacional / Asterisk (`App\Adapters\Conversational\MockAsteriskChatAdapter`):**
   - Simula llamadas de telefonía Asterisk PBX (`simulation_type: asterisk_voice`) transformando transcripts a `AgentInput` y generando respuestas con acciones TTS (`PLAY_TTS`, sin PBX real).
   - Simula sesiones de chat (`simulation_type: chat`) para asistentes y mensajería instantánea.
5. **Rutas Públicas Expuestas:**
   - `POST /api/v1/adapters/webhook`: Recepción genérica de eventos y estímulos externos.
   - `POST /api/v1/adapters/webhook/{agent_id}`: Recepción de eventos con destino explícito a un agente concreto.
   - `POST /api/v1/adapters/mock-asterisk`: Simulación de llamada Asterisk PBX / Voz.
   - `POST /api/v1/adapters/mock-conversational`: Simulación conversacional de chat o voz.

---

## 8. Criterios de Éxito del MVP para `jg_dashboard`

1. **Gestión Completa de Agentes:**
   - Un administrador crea un agente y obtiene un `bootstrap_token` con validez de 24h.
   - Una instancia de `jg_agente` se registra exitosamente mediante dicho token, recibe su credencial permanente y el token queda inutilizable.
   - El agente aparece en la UI/API como `ACTIVE` y sus heartbeats actualizan periódicamente su estado.

2. **Orquestación HITL End-to-End:**
   - Una ejecución iniciada desde el Dashboard que alcanza un `RiskLevel >= HIGH` queda registrada como `WAITING_APPROVAL` en el Dashboard.
   - Un usuario con rol `OPERATOR` o `ADMIN` visualiza el plan propuesto y pulsa "Aprobar".
   - El Dashboard invoca `POST /executions/{thread_id}/approve` en `jg_agente`, permitiendo que el agente complete su ciclo de ejecución.

---

## 9. Decisiones Explícitamente Pospuestas (Fuera de Alcance MVP)

- Soporte de WebSockets / SSE bidireccionales en tiempo real (el MVP utilizará polling eficiente o peticiones HTTP estándar).
- Proveedor de identidad federado externo (SSO, SAML2, OIDC empresarial) — el MVP usará JWT y autenticación local RBAC.
- Conectores avanzados completos de Asterisk VoIP (se limitará a adaptadores HTTP simulados y normalizadores).
- Gestión multicluster de agentes distribuidos geográficamente.
