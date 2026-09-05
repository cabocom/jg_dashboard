# Resumen de Proyecto y Contexto Arquitectónico tras ISSUE-F06-T01

**Fecha:** 5 de septiembre de 2026  
**Documento de referencia:** `PROJECT_CONTEXT.md` (v3.8.0)  
**Hito:** Culminación de `ISSUE-F06-T01` (Cliente HTTP interno para comunicación con jg_agente).

---

## 1. Posicionamiento del Issue en la Arquitectura Global

`jg_dashboard` como Control Plane tiene prohibido expresamente acceder a la base de datos de los agentes del Execution Plane (`jg_agente`). Toda orquestación, consulta de telemetría, inspección de capacidades y aprobaciones HITL se canaliza obligatoriamente a través de peticiones HTTP en la red privada.

Con la implementación de `AgentHttpClient`:
1. El Dashboard dispone de un cliente seguro con firmado HMAC-SHA256 y cabeceras de correlación para interactuar con las APIs internas de los agentes.
2. Se cuenta con tolerancia a fallos transitorios mediante reintentos con backoff y configuración adaptable de timeouts.
3. Se habilita el sustrato para los siguientes issues de la Fase 06:
   - `ISSUE-F06-T02`: Pasarela para disparar ejecuciones (`POST /api/v1/executions/trigger`).
   - `ISSUE-F06-T03`: Consulta de estado de ejecuciones (`GET /api/v1/executions/{agent_id}/{thread_id}`).
   - `ISSUE-F06-T04`: Inspección de capacidades (`GET /api/v1/agents/{agent_id}/capabilities`).

---

## 2. Mapa de Dependencias del Issue
```text
ISSUE-F01-T01 (Tooling & Framework Base)
   └── ISSUE-F06-T01 (Cliente HTTP Interno AgentHttpClient)
          ├── ISSUE-F06-T02 (Pasarela POST /executions/trigger)
          ├── ISSUE-F06-T03 (Consulta GET /executions/{thread_id})
          ├── ISSUE-F06-T04 (Consulta GET /capabilities)
          ├── ISSUE-F07-T02 (Aprobación HITL /approve)
          └── ISSUE-F07-T03 (Rechazo HITL /reject)
```
