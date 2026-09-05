### Resumen de Implementación — ISSUE-F02-T04

Se ha completado la definición de la capa de persistencia y los modelos Eloquent para la gestión de solicitudes Human-In-The-Loop (HITL) en `dashboard_approval_requests` y el registro de auditoría en `dashboard_audit_events` para `jg_dashboard`. Con esta entrega, la **FASE 02 queda completada al 100%**.

#### 1. Componentes Desarrollados y Verificados
- **`database/migrations/2026_09_05_000003_create_dashboard_approval_requests_table.php`:**
  - Migración para la tabla `dashboard_approval_requests`.
  - Definición de clave primaria, `thread_id` indexado, clave foránea `agent_id` referenciando `dashboard_agents` (`nullOnDelete`), `task_id`, estado (`PENDING`, `APPROVED`, `REJECTED`, etc.), nivel de riesgo `risk_level`, campos estructurados JSON para el plan propuesto (`proposed_plan_json`) y comandos de compensación (`compensation_commands_json`), clave foránea `user_id` (`nullOnDelete`), `justification` del operador, timestamp `resolved_at` y auditoría temporal `timestamps`.
- **`database/migrations/2026_09_05_000004_create_dashboard_audit_events_table.php`:**
  - Migración para la tabla `dashboard_audit_events`.
  - Definición de clave primaria, `event_type` indexado, claves foráneas `user_id` y `agent_id` (`nullOnDelete`), `ip_address`, `user_agent`, resumen descriptivo `action_summary`, datos estructurados `payload_json` y timestamp de creación indexado `created_at`.
- **`app/Models/ApprovalRequest.php`:**
  - Modelo Eloquent `ApprovalRequest` con casts para planes y comandos JSON.
  - Relaciones `agent()` y `resolver()` (`User`).
  - Métodos de resolución atómica: `approve(User|int $user, ?string $justification = null)` y `reject(User|int $user, ?string $justification = null)`.
  - Helpers de inspección: `isPending()`, `isApproved()`, `isRejected()`, `isResolved()`.
  - Scopes de consulta: `pending()`, `resolved()`, `approved()`, `rejected()`, `byRisk()`.
- **`app/Models/AuditEvent.php`:**
  - Modelo Eloquent inmutable (`const UPDATED_AT = null`).
  - Helper de conveniencia `AuditEvent::record(...)`.
  - Scopes: `ofType()`, `forAgent()`, `forUser()`.
  - Relaciones con `User` y `Agent`.
- **Modelos `User.php` y `Agent.php` actualizados:**
  - Adición de relaciones inversas `approvalRequests()` y `auditEvents()`.
- **`tests/Unit/ApprovalAndAuditModelTest.php`:**
  - 6 pruebas unitarias en Pest PHP verificando: creación con defaults y casts JSON, flujos completos de aprobación y rechazo registrando justificación y usuario, bloqueo de dobles resoluciones, scopes por riesgo/estado, relaciones inversas y registro estandarizado de auditoría.
- **Entorno de Pruebas (`tests/TestCase.php`):**
  - Incorporación de las tablas `dashboard_approval_requests` y `dashboard_audit_events` en `setUpDatabaseSchema()`.
- **Documentación Técnica:**
  - `PROJECT_CONTEXT.md` (v2.0.0) y `README.md` actualizados marcando la Fase 02 al 100%.
  - Cuarteto documental completo en `docs/issues/ISSUE-F02-T04-*`.

#### 2. Validación y Métricas de Calidad
- **Suite Automatizada (Pest PHP):**
  - **43 pruebas pasando al 100% (197 aserciones)** en 1.49 segundos.

#### 3. Cumplimiento de Criterios de Aceptación
- [x] Tablas creadas con claves foráneas, timestamps e integridad referencial.
- [x] Soporte para almacenar planes propuestos en JSON, comandos de compensación y justificaciones de aprobación/rechazo.
- [x] Batería de pruebas unitarias Pest PHP pasando limpiamente.

/close
