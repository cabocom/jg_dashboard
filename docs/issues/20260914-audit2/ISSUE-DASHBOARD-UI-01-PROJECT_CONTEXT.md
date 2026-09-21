# Contexto del Proyecto: [DASHBOARD-UI-01] Reorganización de la Consola Web: Separación de Ejecución e Historial, Modal de Traza y Acciones Rápidas en Agentes

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#custom-ui-01`
- **Código:** `DASHBOARD-UI-01`
- **Severidad:** 🟡 MEDIO / MEJORA ESTRUCTURAL, FUNCIONAL Y UX
- **Componentes Afectados:**
  - [`jg_dashboard/resources/views/console/index.blade.php`](../../../resources/views/console/index.blade.php)
  - [`jg_dashboard/public/js/console.js`](../../../public/js/console.js)
  - [`jg_dashboard/app/Http/Controllers/WebConsoleController.php`](../../../app/Http/Controllers/WebConsoleController.php)
  - [`jg_dashboard/app/Models/Agent.php`](../../../app/Models/Agent.php)
  - [`jg_dashboard/app/Models/AgentTask.php`](../../../app/Models/AgentTask.php)
  - [`jg_dashboard/tests/Feature/ExecutionViewerWebTest.php`](../../../tests/Feature/ExecutionViewerWebTest.php)

---

## Descripción de Requerimientos y Evolución
1. **Desacople de Flujos:** Separar el lanzamiento de ejecuciones del historial analítico en la consola web (`/console`), creando pestañas y vistas dedicadas (`#view-launcher` e `#view-executions`).
2. **Filtrado Analítico:** Mantener el filtrado por tipo de agente y estado en la barra superior de herramientas del historial.
3. **Columna de Usuario en Historial:** En la tabla de ejecuciones (`#table-executions-history`), mostrar la columna `Usuario` (quién disparó la acción: operador/admin/sistema) en lugar de redundar con el tipo de agente.
4. **Modal Dedicado para "Ver Traza":** Al hacer clic en "Ver Traza" desde el historial, abrir un modal flotante (`#modal-execution-trace`) con la inspección en profundidad de saltos cognitivos (LangGraph) y salida técnica sin abandonar la vista del historial.
5. **Limpieza del Launcher:** Eliminar la barra de consulta manual, selector de agentes duplicado, chips de hilos y metadatos preliminares del panel derecho de `#view-launcher`. Comenzar directamente con los **Saltos Cognitivos y Transiciones de Nodos** en tiempo real.
6. **Métricas y Acciones Rápidas en Tarjetas de Agentes:**
   - Mostrar el número total de ejecuciones (`⚡ ejecuciones`) en cada tarjeta del catálogo.
   - Proporcionar botones de acción directa:
     - `📜 Historial`: Conmuta al historial de ejecuciones filtrando automáticamente por dicho agente.
     - `▶ Nueva Ejecución`: Conmuta a la pasarela de lanzamiento con el desplegable del agente seleccionado automáticamente y foco en el campo de prompt.
