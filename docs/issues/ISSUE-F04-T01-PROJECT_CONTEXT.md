# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fases 01, 02 y 03 al 100%. Fase 04 iniciada (`ISSUE-F04-T01` completado)  
> **Próxima Tarea Inmediata:** `ISSUE-F04-T02` (Endpoint administrativo: POST /api/v1/admin/agents)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Servicios de Agentes y Aprovisionamiento
- `App\Services\Agents\AgentRegistrationService`: Orquestación atómica para la creación de agentes, generación criptográfica de tokens de bootstrap (TTL 24h), almacenamiento exclusivo de hashes SHA-256, validación y regeneración de tokens.
- `App\Services\Auth\JwtService` y `App\Services\Auth\AuthService`: Autenticación JWT y roles RBAC.

### 2.2 Modelos de Persistencia Activos
- `App\Models\Agent`: Agentes registrados (`dashboard_agents`), UUIDs, estados (`PENDING`, `ACTIVE`, `OFFLINE`).
- `App\Models\BootstrapToken`: Tokens de aprovisionamiento 24h (`dashboard_bootstrap_tokens`), un solo uso.
- `App\Models\AuditEvent`: Auditoría del plano de control (`dashboard_audit_events`).
- `App\Models\User`: Usuarios con roles RBAC (`admin`, `operator`, `viewer`).
- `App\Models\ApprovalRequest`: Solicitudes HITL (`dashboard_approval_requests`).
- `App\Models\AgentTask`: Historial de ejecuciones y tareas.

---

## 3. Estado de la Fase 04 (Registro de Agentes y Bootstrap Tokens)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F04-T01` | Servicio de registro de agentes y generación de bootstrap tokens | **COMPLETADO** |
| `ISSUE-F04-T02` | Endpoint administrativo: POST /api/v1/admin/agents | Pendiente |
| `ISSUE-F04-T03` | Endpoint de consumo de registro por el agente: POST /api/v1/agents/register | Pendiente |
| `ISSUE-F04-T04` | Validación de expiración y uso único del bootstrap_token | Pendiente |
