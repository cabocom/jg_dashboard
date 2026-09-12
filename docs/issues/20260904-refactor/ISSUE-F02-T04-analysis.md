# Análisis Técnico: ISSUE-F02-T04 — Modelos de base de datos para solicitudes HITL y eventos de auditoría

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F02-T04`  
> **Fase:** FASE 02 — Persistencia y Modelos de Base de Datos  
> **Prioridad:** High  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F02-T02  

---

## 1. Objetivo del Issue

Definir la capa de persistencia y los modelos Eloquent para la gobernanza Human-In-The-Loop (HITL) y la auditoría de seguridad del plano de control (`jg_dashboard`).
Crear las tablas:
1. `dashboard_approval_requests`: Trazar planes de ejecución propuestos por los agentes que requieren intervención humana por superar umbrales de riesgo, registrando comandos de compensación, decisión del operador y justificación.
2. `dashboard_audit_events`: Trazar de forma inmutable todas las acciones de gestión, aprovisionamiento y seguridad ejecutadas en el Dashboard.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Migración `dashboard_approval_requests` (`database/migrations/2026_09_05_000003_create_dashboard_approval_requests_table.php`):**
   - Campos:
     - `id`: Clave primaria auto-incremental.
     - `thread_id`: Identificador del hilo/checkpoint LangGraph del agente (indexado).
     - `agent_id`: Clave foránea referenciando `dashboard_agents.agent_id` (`nullOnDelete`).
     - `task_id`: Identificador opcional correlacionado con la tarea (indexado).
     - `status`: Estado del flujo (`PENDING`, `APPROVED`, `REJECTED`, `TIMEOUT`, `CANCELLED`), por defecto `PENDING`.
     - `risk_level`: Nivel de riesgo (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`), por defecto `HIGH`.
     - `proposed_plan_json`: Estructura JSON del plan propuesto.
     - `compensation_commands_json`: Comandos de reversión/compensación en caso de fallo (JSON nullable).
     - `user_id`: Clave foránea referenciando `users.id` (el usuario operador/admin que resolvió la solicitud, `nullOnDelete`).
     - `justification`: Justificación escrita por el operador.
     - `resolved_at`: Timestamp en que se resolvió la petición.
     - `timestamps`: `created_at` y `updated_at`.

2. **Migración `dashboard_audit_events` (`database/migrations/2026_09_05_000004_create_dashboard_audit_events_table.php`):**
   - Campos:
     - `id`: Clave primaria.
     - `event_type`: Categoría o tipo de evento indexado (ej: `AGENT_REGISTRATION`, `HITL_APPROVED`, `USER_LOGIN`).
     - `user_id`: Clave foránea hacia `users.id` (nullable, `nullOnDelete`).
     - `agent_id`: Clave foránea hacia `dashboard_agents.agent_id` (nullable, `nullOnDelete`).
     - `ip_address`: Dirección IP del emisor.
     - `user_agent`: Cabecera User-Agent cliente.
     - `action_summary`: Resumen legible de la acción auditada.
     - `payload_json`: Datos estructurados del evento (nullable).
     - `created_at`: Timestamp indexado.

3. **Modelos Eloquent:**
   - `App\Models\ApprovalRequest`: Castings a array, relaciones `agent()` y `resolver()`, transiciones atómicas `approve()` y `reject()`, y scopes `pending()`, `resolved()`, `approved()`, `rejected()`, `byRisk()`.
   - `App\Models\AuditEvent`: Sin `updated_at`, castings a array, relaciones `user()` y `agent()`, método helper `record()`, y scopes `ofType()`, `forAgent()`, `forUser()`.
   - Modelos `User` y `Agent` actualizados con relaciones inversas `approvalRequests()` y `auditEvents()`.

4. **Criterios de Aceptación:**
   - Tablas creadas con claves foráneas, timestamps e integridad referencial.
   - Almacenamiento seguro del plan propuesto y la justificación de aprobación/rechazo.
   - Batería de pruebas unitarias en Pest ejecutando con 100% de éxito.

---

## 3. Decisiones Técnicas y de Diseño

- **Desacoplamiento de Ciclo de Vida:** Si un agente o usuario es eliminado, los registros históricos de aprobación y auditoría no se pierden (`nullOnDelete`), preservando la trazabilidad de seguridad histórica para cumplimiento normativo.
- **Transición Controlada de Estados:** `approve()` y `reject()` exigen que la solicitud se encuentre en estado `PENDING`; una solicitud ya resuelta no puede volver a ser modificada.
- **Inmutabilidad en Auditoría:** La tabla `dashboard_audit_events` no dispone de columna `updated_at` y su modelo desactiva actualizaciones automáticas (`const UPDATED_AT = null`), garantizando el registro de eventos históricos sin modificaciones posteriores.

---

## 4. Plan de Verificación

- Suite Pest PHP incluyendo `Tests\Unit\ApprovalAndAuditModelTest`:
  - `approval request creation with defaults and json casts`
  - `approval request approve flow records operator justification and timestamp`
  - `approval request reject flow records justification`
  - `approval request query scopes`
  - `audit event creation, relations and record helper`
  - `audit event query scopes`
- Ejecución completa de `./scripts/check.sh`.
