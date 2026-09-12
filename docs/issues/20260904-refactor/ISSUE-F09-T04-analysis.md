# Análisis Técnico: [ISSUE-F09-T04] Vista UI: Visor de ejecuciones y traza de pasos en tiempo real

**Issue ID:** ISSUE-F09-T04  
**Fase:** FASE 09 (Interfaz Gráfica Web y Consola HITL)  
**Prioridad:** Medium  
**Complejidad:** M  
**Dependencias:** ISSUE-F06-T03, ISSUE-F09-T01  

---

## 1. Contexto y Objetivos

Una vez disparada una tarea en el Execution Plane (`jg_agente`), los operadores del Control Plane (`jg_dashboard`) necesitan monitorear de manera gráfica la ejecución completa:
1. Conocer el prompt original de entrada (`AgentInput`) que dio origen a la ejecución.
2. Seguir en vivo la línea de tiempo de pasos ejecutados por las herramientas del agente (nodos del grafo, latencia de cada invocación y estado de éxito/error).
3. Inspeccionar el resultado final obtenido (`AgentOutput`) con opciones de copiado directo al portapapeles.
4. Contar con una barra de acceso rápido con chips a los hilos (`thread_id`) disparados o consultados durante la sesión de trabajo.
5. Permitir el refresco automático periódico (polling cada 3 segundos) mientras la tarea esté en progreso (`in_progress`) hasta alcanzar un estado terminal (`completed`, `waiting_approval`, `failed`).

---

## 2. Arquitectura de la Solución

### 2.1 Maquetación Blade e Interfaz (`resources/views/console/index.blade.php`)
- **Sección 3 (`#view-executions`):**
  - Estructura en cuadrícula de dos paneles de alto impacto:
    - **Panel Izquierdo:** Disparador manual de ejecuciones con selector de agente activo, textarea para el prompt, selector de modo síncrono/asíncrono y consola de respuesta HTTP en tiempo real.
    - **Panel Derecho:** Visor interactivo de traza de pasos y telemetría de ejecución.
  - **Barra de Consulta de Hilos (`.execution-query-toolbar`):**
    - Selector desplegable de agentes registrados (`#execution-query-agent`).
    - Campo de texto para `Thread ID` (`#execution-query-thread`).
    - Botón de carga manual `🔍 Cargar Traza` (`#btn-load-execution`).
  - **Barra de Hilos Recientes (`#recent-threads-list`):**
    - Chips interactivos (`.recent-thread-chip`) que registran automáticamente los últimos 5 hilos consultados o disparados en la sesión.
  - **Cuadrícula de Metadatos de Ejecución (`.execution-meta-grid`):**
    - Badges reactivos para Estado (`completed`, `in_progress`, `waiting_approval`, `failed`), Latencia total en milisegundos, Nombre del agente y Thread ID copiable.
  - **Contenedor del Prompt de Entrada (`#execution-prompt-box`):**
    - Muestra el texto o prompt estructurado del usuario.
  - **Línea de Tiempo de Pasos (`#execution-steps-timeline`):**
    - Nodos secuenciales (`.trace-step-node`) con badges numerados, nombre de la herramienta/nodo invocado, estado (éxito/error/ejecución), latencia individual y resumen o output específico de cada paso.
  - **Caja de Resultado Final (`#execution-output-box`):**
    - Bloque de código con resaltado y botón `📋 Copiar` (`#btn-copy-exec-output`) para el payload final.

### 2.2 Estilos CSS (`public/css/console.css`)
- Clases `.execution-layout-grid`, `.recent-threads-bar`, `.recent-thread-chip` con estados hover y active.
- Cuadrícula `.execution-meta-grid` y tarjetas `.exec-meta-item`.
- Línea de tiempo vertical `.trace-steps-timeline` con nodos `.trace-step-node`, conectores visuales y badges de estado (`.trace-node-badge`).
- Bloques de código con scroll horizontal y tipografía monoespaciada `.execution-code-box`.

### 2.3 Lógica Cliente JavaScript (`public/js/console.js`)
- `recentExecutions`: Array en memoria persistido en sesión para los últimos 5 hilos.
- `renderRecentExecutions()`: Renderizado reactivo de los chips de acceso rápido.
- `loadExecutionTrace(agentId, threadId, autoPoll)`: Petición asíncrona a `/console/executions/{agent_id}/{thread_id}` (con fallback transparente a la API REST v1) y gestión del polling automático cada 3 segundos si el estado es `in_progress`.
- `renderExecutionDetail(data)`: Despliegue dinámico de metadatos, prompt, pasos secuenciales del grafo y resultado final.

### 2.4 Endpoints Web de Soporte (`app/Http/Controllers/WebConsoleController.php` y `routes/web.php`)
- `POST /console/executions/trigger`: Dispara la ejecución en el agente a través de `ExecutionController@trigger`, inyectando el operador web por defecto en peticiones de sesión.
- `GET /console/executions/{agent_id}/{thread_id}`: Consulta el estado de ejecución y unifica traza y gobernanza HITL mediante `ExecutionController@show`.

---

## 3. Criterios de Aceptación Cumplidos

1. **Recuperación y visualización del prompt:** El visor expone de forma clara el `prompt` o `user_prompt` de entrada.
2. **Desglose cronológico de pasos:** La línea de tiempo muestra cada paso, herramienta utilizada, latencia y resultado con badges semánticos.
3. **Resultado final:** El output devuelto por `AgentOutput` se formatea en un bloque de código con acción de copiado directo.
4. **Monitoreo en tiempo real:** Polling automático activado en estados no terminales.
5. **Acceso rápido a hilos:** Chips interactivos para saltar entre ejecuciones previas de la sesión.
