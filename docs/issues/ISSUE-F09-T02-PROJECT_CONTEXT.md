# Contexto de Proyecto: [ISSUE-F09-T02] Vista UI: Panel de agentes registrados y monitor de salud

**Componente:** `jg_dashboard` (Control Plane)  
**Versión de Proyecto:** 3.17.0  
**Fecha:** 5 de septiembre de 2026  

---

## 1. Impacto Arquitectónico

La tarea `[ISSUE-F09-T02]` amplía las capacidades operativas del Control Plane al dotarlo de una interfaz de supervisión de nodos cognitivos completa y ergonómica:
- **Observabilidad en Tiempo Real:** Permite a los operadores evaluar de un vistazo el estado operativo de cada agente mediante tarjetas enriquecidas con telemetría (CPU, RAM, latencia en ms) e indicadores de disponibilidad.
- **Aprovisionamiento Guiado:** Proporciona un mecanismo visual para registrar nuevos agentes y emitir tokens bootstrap de un solo uso con caducidad estricta de 24 horas, eliminando la necesidad de comandos manuales de terminal.
- **Seguridad Inmutable:** La persistencia y registro de auditoría (`dashboard_audit_events`) se mantiene intacta garantizando trazabilidad total de qué operador aprovisionó cada agente.

---

## 2. Puntos de Integración

- **Frontend:** `resources/views/console/index.blade.php`, `public/css/console.css`, `public/js/console.js`.
- **Backend Web:** `app/Http/Controllers/WebConsoleController.php` (`storeAgent`).
- **Servicio Central:** `app/Services/Agents/AgentRegistrationService.php` (`createAgent`).
- **Ruta Web:** `POST /console/agents`.
- **Suite de Pruebas:** `tests/Feature/AgentPanelWebTest.php`.
