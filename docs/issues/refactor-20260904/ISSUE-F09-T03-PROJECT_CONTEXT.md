# Contexto de Proyecto: [ISSUE-F09-T03] Vista UI: Consola de aprobación Human-In-The-Loop (HITL)

**Componente:** `jg_dashboard` (Control Plane)  
**Versión de Proyecto:** 3.18.0  
**Fecha:** 5 de septiembre de 2026  

---

## 1. Impacto Arquitectónico

La implementación de `[ISSUE-F09-T03]` materializa la capa visual del pilar de gobernanza **Human-In-The-Loop (HITL)** en el Control Plane:
- **Supervisión Transparente de Grafo Cognitivo:** Los operadores pueden visualizar con precisión quirúrgica el desglose paso a paso de lo que el agente planea ejecutar, qué herramientas invocará y cuáles son los comandos de compensación en caso de fallo.
- **Operación Web Sin Fricción:** Mediante los endpoints web `/console/approvals/...`, el operador puede aprobar o rechazar decisiones en un solo clic directamente en su navegador web sin requerir inyección manual de tokens JWT Bearer.
- **Trazabilidad Total e Inmutable:** Cada decisión queda auditada en `dashboard_audit_events` y delegada hacia el agente interno (`jg_agente`) para reanudar o abortar su grafo LangGraph.

---

## 2. Puntos de Integración

- **Frontend:** `resources/views/console/index.blade.php`, `public/css/console.css`, `public/js/console.js`.
- **Controlador Web:** `app/Http/Controllers/WebConsoleController.php` (`pendingApprovals`, `approveApproval`, `rejectApproval`).
- **Servicio Central:** `app/Services/Hitl/HitlApprovalService.php`.
- **Rutas Web:** `GET /console/approvals/pending`, `POST /console/approvals/{id}/approve`, `POST /console/approvals/{id}/reject`.
- **Suite de Pruebas:** `tests/Feature/HitlConsoleWebTest.php`.
