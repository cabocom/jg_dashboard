# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 (100%) y Fase 02 (100%) COMPLETADAS.  
> **Próxima Tarea Inmediata:** FASE 03 — Autenticación de usuarios y RBAC (`ISSUE-F03-T01`: Middleware de autenticación y verificación de sesión/token).  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Modelos de Persistencia Activos
- `App\Models\User`: Usuarios administradores y operadores, RBAC (`admin`, `operator`, `viewer`), contraseñas hasheadas, relaciones con aprobaciones y auditoría.
- `App\Models\Agent`: Agentes registrados (`dashboard_agents`), UUIDs, estados (`PENDING`, `ACTIVE`, `OFFLINE`), secret hash protegido, heartbeats, capacidades y relaciones.
- `App\Models\BootstrapToken`: Tokens de aprovisionamiento de un solo uso con TTL 24h (`dashboard_bootstrap_tokens`), SHA-256 hash oculto, control de consumo y relación con `Agent`.
- `App\Models\ApprovalRequest`: Solicitudes HITL (`dashboard_approval_requests`), planes propuestos, comandos de compensación, justificación del operador y estados de resolución.
- `App\Models\AuditEvent`: Registro de auditoría inmutable (`dashboard_audit_events`), categorización de eventos, IP, user-agent y payloads JSON contextuales.
- `App\Models\AgentTask`: Historial de ejecuciones y tareas.

### 2.2 Migraciones de Base de Datos
- `0001_01_01_000000_create_users_table.php`
- `2026_09_05_000001_create_dashboard_agents_table.php`
- `2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php`
- `2026_09_05_000003_create_dashboard_approval_requests_table.php`
- `2026_09_05_000004_create_dashboard_audit_events_table.php`

---

## 3. Estado de la Fase 02 (Persistencia y Base de Datos)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F02-T01` | Modelos de base de datos para usuarios y roles RBAC | **COMPLETADO** |
| `ISSUE-F02-T02` | Modelos de base de datos para agentes registrados | **COMPLETADO** |
| `ISSUE-F02-T03` | Modelos de base de datos para tokens de aprovisionamiento (bootstrap_tokens) | **COMPLETADO** |
| `ISSUE-F02-T04` | Modelos de base de datos para solicitudes HITL y eventos de auditoría | **COMPLETADO** |
