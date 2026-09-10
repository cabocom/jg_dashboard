# Análisis Técnico: [ISSUE-F09-T03] Vista UI: Consola de aprobación Human-In-The-Loop (HITL)

**Issue ID:** ISSUE-F09-T03  
**Fase:** FASE 09 (Interfaz Gráfica Web y Consola HITL)  
**Prioridad:** Critical  
**Complejidad:** L  
**Dependencias:** ISSUE-F07-T02, ISSUE-F07-T03, ISSUE-F09-T01  

---

## 1. Contexto y Objetivos

Cuando un agente cognitivo (`jg_agente`) detecta una operación de alto riesgo (`RiskLevel >= HIGH` o `CRITICAL`), pausa su ejecución mediante un checkpoint de LangGraph y emite una solicitud de aprobación HITL al Control Plane (`jg_dashboard`).

El objetivo de esta tarea es dotar a la consola web (`/console`) de una pantalla interactiva y ergonómica que permita a los operadores humanos:
1. Inspeccionar las solicitudes de decisión humana pendientes en tiempo real con sus contadores de severidad (`CRITICAL`, `HIGH`, etc.).
2. Examinar el desglose secuencial de pasos del plan propuesto (`step-by-step timeline`), visualizando la herramienta crítica asignada a cada paso y los comandos exactos a ejecutar.
3. Comprobar los planes de reversión y comandos de compensación automática ante posibles contingencias.
4. Inspeccionar el payload JSON técnico completo a través de un modal dedicado con copiado al portapapeles.
5. Aprobar o rechazar la ejecución con un solo clic recibiendo retroalimentación visual inmediata.

---

## 2. Arquitectura de la Solución

### 2.1 Estructura Blade e Interfaz (`resources/views/console/index.blade.php`)
- **Barra de herramientas HITL (`.hitl-toolbar`):**
  - Pastillas de filtrado por nivel de riesgo: `Todos`, `Crítico` y `Alto` con contadores en vivo.
  - Búsqueda reactiva `#hitl-search` filtrando por hilo, plan o agente.
  - Selector desplegable `#hitl-filter-agent` con los agentes presentes en la cola.
- **Tarjetas de Decisión HITL (`.hitl-card`):**
  - Encabezado con título del plan, nombre del agente, Thread ID interactivo copiable y badge de riesgo.
  - Timeline vertical `.hitl-steps-timeline` con badges numerados para cada paso del plan, acciones y comandos shell destacados.
  - Caja de herramientas críticas requeridas (`.tools-tag-list`).
  - Caja de comandos de reversión/rollback (`.compensation-box`).
  - Indicador de tiempo en pausa ("Pausado hace Xm").
  - Botonera de acción: `Aprobar` (verde), `Rechazar` (rojo) e `Inspeccionar JSON` (cian).
- **Modal de Inspección Técnica (`#modal-inspect-plan`):**
  - Contenedor `#inspect-plan-content` que formatea el JSON estructurado del plan con botón `#btn-copy-plan-json`.

### 2.2 Estilado CSS (`public/css/console.css`)
- Reglas para `.hitl-toolbar`, `.hitl-pills-container`, `.hitl-pill-btn` (estados activos diferenciados para `CRITICAL` en rosa luminiscente y `HIGH` en ámbar).
- Estructura vertical `.hitl-steps-timeline` con línea guía y círculos numerados `.step-badge`.
- Contenedores de comandos shell `.step-command-box` y reversión `.compensation-box`.
- Caja de inspección JSON `.json-inspector-box` con fondo translúcido y scrollbar integrado.

### 2.3 Lógica Cliente JavaScript (`public/js/console.js`)
- `extractPlanSteps(app)`: Normaliza el plan extrayendo pasos secuenciales tanto de arrays estructurados (`steps`, `actions`) como de planes planos.
- `renderFilteredApprovals()`: Filtrado reactivo en cliente por nivel de riesgo, agente emisor y búsqueda textual.
- `openInspectPlanModal(id)`: Despliega el visor modal con el JSON crudo del plan.
- `submitApproval()` y `submitRejection()`: Envío asíncrono hacia `/console/approvals/{id}/approve` y `/console/approvals/{id}/reject` (con fallback transparente hacia `/api/v1/approvals/...`), confirmación toast y re-renderizado sin refresco de página.

### 2.4 Endpoints Web de Soporte (`app/Http/Controllers/WebConsoleController.php` y `routes/web.php`)
- `GET /console/approvals/pending`: Retorna el listado paginado y formateado de aprobaciones con métricas de resumen.
- `POST /console/approvals/{id}/approve`: Delega la aprobación hacia `jg_agente` asociando al operador web autenticado.
- `POST /console/approvals/{id}/reject`: Valida el motivo de rechazo (mínimo 5 caracteres) y delega la cancelación en el agente.

---

## 3. Criterios de Aceptación Verificados

- [x] Pantalla interactiva que muestra planes pendientes de aprobación con desglose de pasos y nivel de riesgo.
- [x] Operador puede revisar el plan de ejecución y aprobar o rechazar con un solo clic.
- [x] Confirmación visual inmediata (toasts y actualización reactiva de tarjetas y contadores).
- [x] Inspector JSON del plan accesible desde cada tarjeta.
- [x] Cobertura de pruebas automatizadas completa con 100% de tests pasando en Pest.
