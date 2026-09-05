# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 (100%), Fase 02 (100%) y Fase 03 (100%) COMPLETADAS.  
> **Próxima Tarea Inmediata:** FASE 04 — Sistema de Registro de Agentes (`ISSUE-F04-T01`: Servicio de registro de agentes y generación de bootstrap tokens con TTL 24h).  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Capa de Autenticación, RBAC y CLI
- `App\Console\Commands\InitAdminCommand` (`php artisan dashboard:init-admin`): Aprovisionamiento idempotente de administrador.
- `scripts/init_admin.sh`: Script de arranque automatizado.
- `App\Services\Auth\JwtService`: Emisión y validación RFC 7519 HMAC-SHA256.
- `App\Services\Auth\AuthService`: Hashing y verificación de credenciales.
- `App\Http\Middleware\JwtAuthenticate` (`jwt.auth`), `RequireRole` (`role`), `RequirePermission` (`permission`).
- `App\Http\Controllers\Api\AuthController`: `login`, `me`, `logout`.

### 2.2 Modelos de Persistencia Activos
- `App\Models\User`: RBAC completo (`admin`, `operator`, `viewer`), contraseñas hasheadas, permisos dinámicos y relaciones.
- `App\Models\Agent`: Agentes registrados (`dashboard_agents`).
- `App\Models\BootstrapToken`: Tokens de aprovisionamiento 24h (`dashboard_bootstrap_tokens`).
- `App\Models\ApprovalRequest`: Solicitudes HITL (`dashboard_approval_requests`).
- `App\Models\AuditEvent`: Auditoría del plano de control (`dashboard_audit_events`).
- `App\Models\AgentTask`: Historial de ejecuciones y tareas.

---

## 3. Estado de la Fase 03 (Autenticación y RBAC)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F03-T01` | Servicio de autenticación JWT y hashing de contraseñas | **COMPLETADO** |
| `ISSUE-F03-T02` | Middleware y dependencias de autorización RBAC | **COMPLETADO** |
| `ISSUE-F03-T03` | Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me | **COMPLETADO** |
| `ISSUE-F03-T04` | Script de inicialización para creación del usuario administrador inicial | **COMPLETADO** |
