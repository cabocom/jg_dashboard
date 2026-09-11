# Contexto de Proyecto: [ISSUE-DASH-004] Monitorización de salud basada en timestamp real de heartbeat

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-004
- **GitLab Work Item:** jg_dashboard#39
- **Fase:** Fase Post-Auditoría (Observabilidad y Estado Real en Consola Web)
- **Prioridad:** Medium
- **Complejidad Estimada:** S
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
En la auditoría técnica independiente (FINDINGS.md, DASH-004), se detectó que `WebConsoleController::listAgents` empleaba una condición `||` que marcaba a cualquier agente como saludable si su estado en base de datos era `ACTIVE`, independientemente de la antigüedad de su latido:
`'is_healthy' => $agent->status === 'ACTIVE' || $agent->isHealthy(90)`

Consecuencias detectadas:
1. Un agente caído o congelado seguía mostrándose como `ACTIVE` y saludable en las tarjetas de la consola web hasta que el comando programado en cron `agents:check-health` detectara el timeout y actualizara el registro en la base de datos.
2. El selector de disparo de tareas (`POST /console/executions/trigger`) permitía seleccionar agentes caídos al figurar como `ACTIVE`.
3. Los contadores KPI pre-renderizados en la vista inicial y en la respuesta JSON no reflejaban la realidad operativa del clúster.

Para resolver este problema:
- **Evaluación Estricta de Salud**: `is_healthy` se evalúa mediante `$agent->status === 'ACTIVE' && $agent->isHealthy(90)`.
- **Estado Efectivo Dinámico**: Si un agente está marcado como `ACTIVE` en BD pero no cumple `isHealthy(90)` (por haber superado los 90 segundos sin heartbeat o tener timestamp nulo), su estado reportado a la consola web se conmuta a `OFFLINE`.
- **Métricas de Resumen Coherentes**: Los contadores agregados `active` y `offline` en `listAgents()` y en la vista inicial `index()` se calculan en base al estado efectivo en tiempo real.
- **Frontend Consistente**: Se retiró el fallback nullish coalescing en `console.js` para asegurar que el estado visual dependa exclusivamente del valor booleano estricto de `is_healthy`.

## 3. Criterios de Aceptación
- Un agente cuyo último heartbeat supere los 90 segundos figura como `OFFLINE` en `/console/agents` y en la consola web, con `is_healthy: false`.
- Un agente activo con heartbeat reciente (<= 90s) figura como `ACTIVE`, con `is_healthy: true`.
- Los contadores de resumen `summary.active` y `summary.offline` reflejan la disponibilidad real sin depender de la periodicidad del cron scheduler.
- La suite completa de pruebas de Pest en `jg_dashboard` supera los tests unitarios y de integración.
