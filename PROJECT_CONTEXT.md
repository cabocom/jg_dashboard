# CONTEXTO DE PROYECTO: jg_dashboard

**Versión del documento:** 3.7.0  
**Fecha de consolidación:** 5 de septiembre de 2026  
**Componente:** Control Plane (Plano de Control, Gestión, Gobernanza y HITL)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, Tailwind CSS, PostgreSQL, Pest PHP  
**Estado:** Fases 01, 02, 03, 04 y 05 completadas al 100%. Fase 05 culminada con ISSUE-F05-T03 (Endpoint de listado y estado de salud GET /api/v1/agents con paginación, filtros por estado, búsqueda, métricas agregadas y detalle por agente sin exposición de secretos). 127 tests pasando en Pest.  

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

### 5.1 Endpoints Expuestos por `jg_dashboard` para Agentes
- `POST /api/v1/agents/register`: Validación del bootstrap token y emisión de credenciales permanentes.
- `POST /api/v1/agents/heartbeat`: Recepción de estado de salud y métricas de agentes activos.
- `POST /api/v1/agents/events`: Recepción de eventos asíncronos del ciclo de vida (`execution.started`, `execution.waiting_approval`, etc.).

### 5.2 Endpoints Invocados por `jg_dashboard` hacia `jg_agente` (Proxy Interno)
- `POST /executions/trigger`: Envía un `AgentInput` para disparar una ejecución.
- `GET /executions/{thread_id}`: Consulta estado detallado y traza de pasos de una ejecución.
- `POST /executions/{thread_id}/approve`: Reanuda una ejecución detenida en el checkpoint HITL.
- `POST /executions/{thread_id}/reject`: Cancela una ejecución detenida en HITL.
- `GET /capabilities`: Obtiene el catálogo de herramientas y capacidades del agente.

---

## 6. Persistencia de Datos del Dashboard

El Dashboard cuenta con su **propia base de datos PostgreSQL**, completamente desacoplada de los agentes:
- `dashboard_users`: Usuarios administradores, operadores y credenciales con hash Argon2/Bcrypt.
- `dashboard_roles` y `dashboard_permissions`: Definición de perfiles RBAC (`ADMIN`, `OPERATOR`, `VIEWER`).
- `dashboard_agents`: Registro de agentes, nombres, estado (`PENDING`, `ACTIVE`, `OFFLINE`), hash de credencial permanente y metadatos.
- `dashboard_bootstrap_tokens`: Registro de tokens de aprovisionamiento con timestamp de creación, expiración (24h) y estado de uso.
- `dashboard_approval_requests`: Registro histórico de planes pendientes de aprobación humana, usuario aprobador/rechazador, timestamp y justificación.
- `dashboard_audit_events`: Registro de auditoría de todas las acciones administrativas realizadas en el Dashboard.

---

## 7. Módulo de Adaptadores Normalizadores (I/O Gateway)

Para atender a la directriz arquitectónica que prohíbe interfaces externas en el agente, `jg_dashboard` proporciona una capa modular de adaptadores:
1. **Adaptador Web/REST:** Recibe peticiones HTTP externas autenticadas de aplicaciones cliente y las serializa en `AgentInput`.
2. **Adaptador de Mensajería/Voz (Fase futura):** Traduce eventos de Asterisk o mensajería a texto plano normalizado con `thread_id` correlacionado.
3. El agente responde `AgentOutput` y el adaptador formatea la respuesta específica para el protocolo emisor.

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
