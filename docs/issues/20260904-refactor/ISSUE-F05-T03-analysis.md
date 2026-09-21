# Análisis Técnico: ISSUE-F05-T03 — Endpoint de listado y estado de agentes: GET /api/v1/agents

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F05-T03`  
> **Fase:** FASE 05 — Monitoreo de Agentes y Heartbeats  
> **Prioridad:** Medium  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F05-T02  

---

## 1. Objetivo del Issue

Implementar y exponer el endpoint protegido `GET /api/v1/agents` en `jg_dashboard` para que los usuarios autenticados (con roles `ADMIN`, `OPERATOR` y `VIEWER`) puedan consultar la lista paginada de agentes registrados, su estado actual (`ACTIVE`, `OFFLINE`, `PENDING`), indicadores de salud recientes (si reportó en los últimos 90 segundos) y métricas agregadas globales del ecosistema.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Endpoint `GET /api/v1/agents`:**
   - **Autenticación y RBAC:**
     - Protegido por `jwt.auth`.
     - Accesible por cualquier usuario autenticado (`viewer`, `operator`, `admin`).
     - Peticiones no autenticadas devuelven `401 Unauthorized`.
   - **Parámetros de Consulta (Query Params):**
     - `status`: Filtrado por estado (`ACTIVE`, `OFFLINE`, `PENDING`).
     - `search`: Búsqueda parcial por nombre o coincidencia exacta de `agent_id`.
     - `per_page`: Control de paginación (default 15, máx. 100).
   - **Estructura de Respuesta (200 OK):**
     - `data`: Colección de agentes con: `agent_id`, `name`, `status`, `version`, `is_healthy` (booleano), `last_heartbeat_at`, `capabilities`, `metrics` y `created_at`.
     - `summary`: Métricas agregadas en tiempo real (`total`, `active`, `offline`, `pending`).
     - `meta`: Paginación (`current_page`, `last_page`, `per_page`, `total`).
   - **Seguridad:** Nunca exponer `permanent_secret_hash` ni tokens de aprovisionamiento en la salida.

2. **Endpoint de Detalle Individual `GET /api/v1/agents/{agent_id}`:**
   - Consulta el estado completo, metadatos y última telemetría de un agente puntual (código `200 OK` o `404 Not Found`).

3. **Criterios de Aceptación:**
   - Endpoint devuelve lista paginada de agentes con detalles de última conexión y métricas de salud.
   - 100% de pruebas pasando en Pest PHP.

---

## 3. Decisiones Técnicas y de Arquitectura

- **Controlador REST Dedicado ([app/Http/Controllers/Api/AgentController.php](../../../app/Http/Controllers/Api/AgentController.php)):** Desacoplado del controlador administrativo de creación y enfocado en la consulta, supervisión y observabilidad del estado de los agentes.
- **Cálculo Dinámico de Salud:** Mediante `$agent->isHealthy(90)` se computa al vuelo si el agente está realmente vivo (activo con reporte en <90s) independientemente del estado en BD.
- **Acceso Democrático RBAC:** Mientras que la mutación o creación de agentes es exclusiva de `admin`, la consulta de estado y salud está disponible para `viewer` y `operator`.
