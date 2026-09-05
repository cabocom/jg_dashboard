# Análisis Técnico: [ISSUE-F07-T02] Endpoint de listado de aprobaciones pendientes: GET /api/v1/approvals/pending

**Issue ID:** ISSUE-F07-T02  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Prioridad:** High  
**Complejidad:** S  
**Dependencias:** ISSUE-F07-T01  

---

## 1. Contexto y Objetivos

Para que los operadores humanos y administradores del plano de control puedan supervisar eficazmente las ejecuciones cognitivas en pausa que requieren intervención (`waiting_approval`), el Dashboard debe proporcionar una API REST dedicada que liste todas las solicitudes en estado `PENDING`.

La respuesta debe entregar información contextual suficiente para que un operador evalúe de inmediato la criticidad y naturaleza de la solicitud:
- Resumen inteligible de las acciones o comandos propuestos (`action_summary`).
- Nivel de riesgo asignado (`risk_level`: `CRITICAL`, `HIGH`, etc.).
- Herramientas involucradas (`tools_involved`).
- Plan completo y comandos de mitigación/rollback (`compensation_commands`).
- Métricas temporales precisas: tiempo transcurrido relativo (`elapsed_time`) y en segundos absolutos (`elapsed_seconds`).
- Metadatos de paginación y métricas agregadas (`summary`).

---

## 2. Arquitectura y Diseño

### 2.1 Seguridad y Autorización
- **Ruta Principal:** `GET /api/v1/approvals/pending`
- **Ruta Alias:** `GET /api/approvals/pending`
- **Control de Acceso:** Middleware `jwt.auth` y `role:operator`.
  - Usuarios con rol `operator` y `admin` tienen acceso autorizado (jerarquía RBAC).
  - Usuarios con rol `viewer` reciben `403 Forbidden`.
  - Peticiones anónimas reciben `401 Unauthorized`.

### 2.2 Parámetros de Consulta
- `agent_id` (opcional): Filtra solicitudes originadas por un agente específico.
- `risk_level` (opcional): Filtra por severidad de riesgo (`CRITICAL`, `HIGH`, etc.).
- `per_page` (opcional, default: 15, max: 100): Control de tamaño de página.

### 2.3 Capa de Servicio y Normalización
- `HitlApprovalService::getPendingApprovals()` recupera las solicitudes con estado `PENDING`, ordenadas descendentemente por fecha de creación.
- `HitlApprovalService::formatApprovalItem()` construye el payload canónico para cada elemento, calculando el resumen de acciones (`extractActionSummary()`), cargando las herramientas involucradas y calculando el tiempo transcurrido desde el momento en que se detuvo la ejecución.

---

## 3. Criterios de Aceptación Verificados

- [x] Endpoint `GET /api/v1/approvals/pending` expuesto bajo autenticación JWT y RBAC (`operator` / `admin`).
- [x] Retorna lista paginada de ejecuciones en espera con resumen de acciones, nivel de riesgo y tiempo transcurrido (`elapsed_time` y `elapsed_seconds`).
- [x] Incluye catálogo de herramientas involucradas y comandos de compensación.
- [x] Admite filtrado opcional por `agent_id` y por `risk_level`.
- [x] Entrega métricas agregadas en bloque `summary` (total pendientes, conteo crítico y alto).
- [x] 100% de pruebas automáticas superadas en `./scripts/check.sh`.
