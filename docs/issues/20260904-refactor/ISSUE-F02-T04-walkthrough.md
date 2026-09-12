# Walkthrough: ISSUE-F02-T04 — Modelos de base de datos para solicitudes HITL y eventos de auditoría

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el modelo y las migraciones para la gestión de solicitudes Human-In-The-Loop (HITL) y la auditoría del plano de control en `jg_dashboard`:

1. **Migraciones de Base de Datos:**
   - [database/migrations/2026_09_05_000003_create_dashboard_approval_requests_table.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/database/migrations/2026_09_05_000003_create_dashboard_approval_requests_table.php):
     - Tabla `dashboard_approval_requests` con claves foráneas hacia `dashboard_agents` y `users`.
     - Soporte para estados (`PENDING`, `APPROVED`, `REJECTED`, etc.), niveles de riesgo (`risk_level`), plan propuesto en JSON (`proposed_plan_json`), comandos de compensación (`compensation_commands_json`), justificación del operador y timestamp de resolución.
   - [database/migrations/2026_09_05_000004_create_dashboard_audit_events_table.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/database/migrations/2026_09_05_000004_create_dashboard_audit_events_table.php):
     - Tabla `dashboard_audit_events` inmutable con `event_type`, relaciones con `users` y `dashboard_agents`, IP, user-agent, resumen de la acción, payload JSON y timestamp de creación.

2. **Modelos Eloquent:**
   - [app/Models/ApprovalRequest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/ApprovalRequest.php):
     - Casts para JSON y fechas.
     - Métodos de resolución atómica: `approve()` y `reject()` con validación de estado previo.
     - Scopes de consulta: `pending()`, `resolved()`, `approved()`, `rejected()`, `byRisk()`.
     - Relaciones `agent()` y `resolver()`.
   - [app/Models/AuditEvent.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/AuditEvent.php):
     - Inmutabilidad (`const UPDATED_AT = null`).
     - Helper estático `record()` para inserciones uniformes.
     - Scopes: `ofType()`, `forAgent()`, `forUser()`.
     - Relaciones `user()` y `agent()`.
   - Modelos actualizados:
     - [app/Models/User.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/User.php): relaciones `approvalRequests()` y `auditEvents()`.
     - [app/Models/Agent.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/Agent.php): relaciones `approvalRequests()` y `auditEvents()`.

3. **Suite de Pruebas Unitarias:**
   - [tests/Unit/ApprovalAndAuditModelTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Unit/ApprovalAndAuditModelTest.php): 6 pruebas unitarias verificando creación, flujos de aprobación y rechazo con justificación, prevención de doble resolución, scopes de búsqueda y registro de auditoría.

4. **Entorno de Pruebas en Memoria:**
   - [tests/TestCase.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/TestCase.php): Esquemas `dashboard_approval_requests` y `dashboard_audit_events` añadidos en `setUpDatabaseSchema()`.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentModelTest
  ✓ agent creation and uuid generation                                   0.21s  
  ✓ agent hidden attributes in serialization                             0.18s  
  ✓ agent scopes                                                         0.01s  
  ✓ agent health check recency                                           0.01s  
  ✓ agent state transitions                                              0.18s  

   PASS  Tests\Unit\ApprovalAndAuditModelTest
  ✓ approval request creation with defaults and json casts               0.10s  
  ✓ approval request approve flow records operator justification and ti… 0.01s  
  ✓ approval request reject flow records justification                   0.01s  
  ✓ approval request query scopes
  ✓ audit event creation, relations and record helper                    0.01s  
  ✓ audit event query scopes                                             0.01s  

   PASS  Tests\Unit\BootstrapTokenModelTest
  ✓ bootstrap token creation and relationship with agent                 0.01s  
  ✓ bootstrap token hides token_hash when serialized
  ✓ bootstrap token expiration detection with 24h TTL
  ✓ bootstrap token one-time consumption mechanics
  ✓ expired token cannot be consumed
  ✓ bootstrap token query scopes
  ✓ createTokenForAgent helper generates secure plaintext and persists…  0.01s  

   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    43 passed (197 assertions)
  Duration: 1.49s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Conclusión de la Fase 02

Con la finalización de este issue, la **FASE 02 (Persistencia y Modelos de Base de Datos)** queda completada al 100%. Todos los modelos del Control Plane se encuentran plenamente operativos, testeados y respaldados por migraciones nativas de PostgreSQL.
