# [ISSUE-34] [jg_dashboard] refactor(solid): Modularizar monolito public/js/console.js (2,747 líneas) y segregar las 7 clases PHP con >10 métodos públicos

- **ID Maestro QA:** `ISSUE-34`
- **Proyecto Destino:** `javiergalvez-ia/jg_dashboard`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::critical, solid, sonar-debt`
- **GitLab Issue Remoto:** [jg_dashboard#64](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/64)

---

- **Severidad y Categoría:** `Critical` | `Complejidad-CodeSmell`
- **Consenso de Auditores:** Detectado por: `hydrogen` (`public/js/console.js`), `argon` (7 clases con >10 métodos públicos y violación SRP en `AgentController`)
- **Evidencia Exacta:**
  - `jg_dashboard/public/js/console.js:1-2747` (`2,747` líneas)
  - `jg_dashboard/app/Services/Agents/AgentHttpClient.php` (20 métodos), `ApprovalRequest.php` (17), `Agent.php` (15), `JwtService.php` (13), `WebConsoleController.php` (12), `AgentTask.php` (11), `BootstrapToken.php` (11)
  - `jg_dashboard/app/Http/Controllers/Api/AgentController.php:24-82, 116-171`
- **Solución Técnica Propuesta:**
  1. Modularizar `public/js/console.js` en módulos ES6 separados por responsabilidad (`api.js`, `state.js`, `telemetry.js`, `hitl.js`, `tabs.js`), ninguno superior a 300 líneas.
  2. Extraer las operaciones de `AgentController::index` y `capabilities` a clases `Action` dedicadas y reducir los métodos públicos de las 7 clases señaladas a `<= 10`.
- **Criterios de Aceptación (Checklist verificable):**
  - [x] Ningún archivo JS o clase PHP en `jg_dashboard` supera las 300 líneas ni los 10 métodos públicos.
