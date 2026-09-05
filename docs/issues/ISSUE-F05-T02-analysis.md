# Análisis Técnico: ISSUE-F05-T02 — Servicio en background de detección de agentes caídos (Health Monitor)

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F05-T02`  
> **Fase:** FASE 05 — Monitoreo de Agentes y Heartbeats  
> **Prioridad:** High  
> **Complejidad Estimada:** M  
> **Dependencias:** ISSUE-F05-T01  

---

## 1. Objetivo del Issue

Implementar la tarea en segundo plano y el servicio supervisor (`AgentHealthMonitorService`) que identifique de forma recurrente y determinista los agentes activos que lleven más de 90 segundos sin emitir un heartbeat al Control Plane, transicionándolos automáticamente al estado `OFFLINE` y registrando el correspondiente evento de auditoría en la base de datos PostgreSQL.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Detección Automática de Inactividad:**
   - Consulta sobre la tabla `dashboard_agents` para agentes en estado `ACTIVE` donde `last_heartbeat_at` sea nulo o inferior a `now() - 90 segundos` (`scopeTimedOut`).
   - Transición de estado a `OFFLINE` mediante `$agent->markAsOffline()`.
   - Registro de auditoría `AGENT_OFFLINE` en `dashboard_audit_events` preservando el timestamp previo y el umbral evaluado.

2. **Ejecución Programada y CLI:**
   - Comando Artisan `agents:check-health` con opción configurable `--timeout=90`.
   - Programación periódica en el Scheduler de Laravel (`Schedule::command(...)->everyMinute()`) en `routes/console.php`.
   - Script helper ejecutable `scripts/check_health.sh`.

3. **Criterios de Aceptación:**
   - Agente sin reporte durante más de 90 segundos cambia automáticamente a estado OFFLINE en la base de datos.
   - 100% de pruebas pasando en Pest PHP.

---

## 3. Decisiones Técnicas y de Arquitectura

- **Desacoplamiento en Capa de Servicio ([app/Services/Agents/AgentHealthMonitorService.php](file:///Users/javiergalvez-ia/jg_dashboard/app/Services/Agents/AgentHealthMonitorService.php)):** Toda la lógica de detección y transición reside en un servicio reutilizable y testeable de forma independiente del entorno de ejecución (CLI, Worker o Web).
- **Alcance Selectivo de Estados:** Solo los agentes que estuvieron previamente operativos (`ACTIVE`) son evaluados para transición a `OFFLINE`. Los agentes no aprovisionados (`PENDING`) se mantienen intactos.
- **Transaccionalidad Atómica:** Las actualizaciones de estado y los registros de auditoría se encapsulan en transacciones de base de datos para garantizar consistencia.
