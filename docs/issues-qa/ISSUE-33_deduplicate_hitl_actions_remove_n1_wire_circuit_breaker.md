# [ISSUE-33] [jg_dashboard] refactor(hitl): Eliminar clon de 150 líneas entre ApproveHitlRequestAction y RejectHitlRequestAction, eliminar N+1 HTTP en HitlApprovalFormatter y conectar CircuitBreakerService

- **ID Maestro QA:** `ISSUE-33`
- **Proyecto Destino:** `javiergalvez-ia/jg_dashboard`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::high, bug, dry, sonar-debt`
- **GitLab Issue Remoto:** [jg_dashboard#63](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/63)

---

- **Severidad y Categoría:** `Major` | `DRY-Duplicación` / `Fiabilidad-Bug`
- **Consenso de Auditores:** Detectado por: `argon`, `hydrogen`, `claude-sonnet`, `flash-3.8`
- **Evidencia Exacta:**
  - `jg_dashboard/app/Actions/Hitl/ApproveHitlRequestAction.php:42-246` vs `RejectHitlRequestAction.php:42-248`
  - `jg_dashboard/app/Services/Hitl/HitlApprovalFormatter.php:67-107`
  - `jg_dashboard/app/Actions/Console/ListConsoleExecutionsAction.php:69, 76, 169-171` y `TopologyMap.php:77-79`
  - `jg_dashboard/app/Services/Resilience/CircuitBreakerService.php:18-246`
- **Solución Técnica Propuesta:**
  1. Extraer una clase base o servicio `ResolveHitlRequestService` que encapsule el bloqueo transaccional, despacho HTTP, reversión a `PENDING` y reconciliación 404/409, reduciendo `ApproveHitlRequestAction` y `RejectHitlRequestAction` a `< 40` líneas cada una.
  2. Eliminar la llamada HTTP `$client->getExecutionStatus(...)` y el `$approval->update(...)` de `HitlApprovalFormatter::formatApprovalItem` (moviendo la hidratación del plan al momento de registrar la solicitud en `RegisterHitlApprovalAction`).
  3. Integrar `CircuitBreakerService` en `AgentHttpRequestDispatcher` y añadir logging a los bloques `catch` vacíos de `ListConsoleExecutionsAction` y `TopologyMap`.
- **Criterios de Aceptación (Checklist verificable):**
  - [ ] `php artisan test` en `jg_dashboard` (352 tests) pasa al 100%.
