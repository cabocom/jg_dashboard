# Análisis Técnico: [ISSUE-F09-T02] Vista UI: Panel de agentes registrados y monitor de salud

**Issue ID:** ISSUE-F09-T02  
**Fase:** FASE 09 (Interfaz Gráfica Web y Consola HITL)  
**Prioridad:** High  
**Complejidad:** M  
**Dependencias:** ISSUE-F05-T03, ISSUE-F09-T01  

---

## 1. Contexto y Objetivos

El objetivo de este issue es construir el panel visual interactivo de agentes y monitor de salud dentro de la consola web (`/console`), permitiendo a los operadores:
1. Visualizar el inventario de nodos cognitivos en dos modos conmutables: **Tarjetas Grid** (vista principal enriquecida) y **Tabla de Datos** (vista densa para inventario).
2. Monitorear en tiempo real el estado (`ACTIVE`, `OFFLINE`, `PENDING`), indicador de salud (`is_healthy`), telemetría (CPU, RAM, latencia en ms) y último latido (`last_heartbeat_at`).
3. Disponer de herramientas de filtrado dinámico: búsqueda en vivo por texto (nombre o ID del agente) y selector de estado (`ALL`, `ACTIVE`, `OFFLINE`, `PENDING`).
4. Proporcionar un flujo de **Aprovisionamiento de Agentes** mediante modal interactivo que genera un **Bootstrap Token** de un solo uso con caducidad estricta de 24 horas y botón de copiado rápido al portapapeles.

---

## 2. Arquitectura de la Solución

### 2.1 Componentes UI en Blade (`resources/views/console/index.blade.php`)
- **Barra de herramientas (`.agents-toolbar`):**
  - Input de búsqueda reactiva `#agents-search`.
  - Dropdown selector de estado `#agents-filter-status`.
  - Conmutador de vista (`.view-btn[data-view="cards"]` vs `.view-btn[data-view="table"]`).
  - Botón de acción principal `#btn-create-agent` para abrir el modal de aprovisionamiento.
- **Contenedores de visualización:**
  - `#agents-cards-container`: Grid responsivo (`.agents-cards-grid`) con tarjetas glassmórficas.
  - `#agents-table-container`: Panel con tabla semántica `#agents-table-body`.
- **Modal de Creación y Aprovisionamiento (`#modal-create-agent`):**
  - Formulario `#form-create-agent` con campos obligatorios (`name`, `version`).
  - Panel de resultado `#create-agent-result-box` con advertencia de secreto de un solo uso, ID del agente, token en claro y botón `#btn-copy-token`.

### 2.2 Estilado y Animaciones (`public/css/console.css`)
- Estilos para `.agent-card` con bordes luminosos según estado (`status-active` verde esmeralda, `status-pending` ámbar, `status-offline` tenue).
- Panel de telemetría interna `.agent-telemetry-row` mostrando métricas de CPU, RAM y latencia.
- Contenedor de visualización de token `.token-result-box` con caja punteada y estilo monoespaciado.

### 2.3 Lógica en Cliente (`public/js/console.js`)
- Métodos añadidos y actualizados:
  - `setAgentViewMode(mode)`: Alterna la visibilidad entre el grid de tarjetas y la tabla.
  - `renderFilteredAgents()`: Aplica los filtros de búsqueda por nombre/ID y estado antes de delegar a `renderAgentsCards()` y `renderAgentsTable()`.
  - `openCreateAgentModal()`, `submitCreateAgent()`: Gestiona el envío asíncrono hacia `POST /console/agents` y la visualización del token generado.
  - `copyToClipboard()`: Copia al portapapeles con fallback para entornos no seguros.

### 2.4 Endpoint de Aprovisionamiento Web (`app/Http/Controllers/WebConsoleController.php`)
- Método `storeAgent(Request $request, AgentRegistrationService $service)`:
  - Valida el payload de entrada (`name`, `version`, `ttl_hours`).
  - Invoca a `AgentRegistrationService::createAgent()` que persiste el agente en estado `PENDING`, genera el Bootstrap Token hasheado en base de datos y emite el evento de auditoría `AGENT_CREATED`.
  - Retorna HTTP 201 Created con el token en texto plano (mostrado una única vez).
- Ruta registrada en `routes/web.php`: `POST /console/agents` (`console.agents.store`).

---

## 3. Criterios de Aceptación Verificados

- [x] Panel de agentes muestra tarjetas y tabla con actualización periódica de estado.
- [x] Conmutador de vistas Tarjetas / Tabla funcional.
- [x] Filtros en tiempo real por búsqueda y estado operativo.
- [x] Modal de provisión que genera y presenta el Bootstrap Token de 24h.
- [x] Endpoint `POST /console/agents` protegido y validado.
- [x] Suite de pruebas automatizadas con 100% de tests en verde.
