# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 (100%), Fase 02 (100%), Fase 03 avanzada (`ISSUE-F03-T01` e `ISSUE-F03-T02` completados)  
> **Próxima Tarea Inmediata:** `ISSUE-F03-T03` (Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Capa de Seguridad y Autorización
- `App\Http\Middleware\JwtAuthenticate` (`jwt.auth`): Middleware que intercepta tokens Bearer, valida firmas HS256 / caducidad y resuelve el usuario autenticado.
- `App\Http\Middleware\RequireRole` (`role`): Middleware de autorización RBAC con jerarquía de roles (`ADMIN`, `OPERATOR`, `VIEWER`).
- `App\Http\Middleware\RequirePermission` (`permission`): Middleware para control granular de permisos del panel de control.
- `App\Services\Auth\JwtService` y `App\Services\Auth\AuthService`: Criptografía RFC 7519 y verificación de credenciales.

### 2.2 Modelos de Persistencia Activos
- `App\Models\User`: RBAC completo (`admin`, `operator`, `viewer`), contraseñas hasheadas, métodos `hasRole()`, `hasPermission()`, `isAdmin()`, `isOperator()`, `isViewer()`.
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
| `ISSUE-F03-T03` | Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me | Pendiente |
| `ISSUE-F03-T04` | Script de inicialización para creación del usuario administrador inicial | Pendiente |
