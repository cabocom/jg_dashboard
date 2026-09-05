# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 (100%), Fase 02 (100%), Fase 03 avanzada (`ISSUE-F03-T01`, `ISSUE-F03-T02` e `ISSUE-F03-T03` completados)  
> **Próxima Tarea Inmediata:** `ISSUE-F03-T04` (Script de inicialización para creación del usuario administrador inicial)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Endpoints de Autenticación Activos
- `POST /api/v1/auth/login` y `POST /api/auth/login`: Autenticación con email/password y retorno de JWT Bearer.
- `GET /api/v1/auth/me` y `GET /api/auth/me`: Inspección de perfil autenticado, rol y catálogo de permisos.
- `POST /api/v1/auth/logout` y `POST /api/auth/logout`: Cierre de sesión.

### 2.2 Capa de Seguridad y Autorización
- `App\Http\Middleware\JwtAuthenticate` (`jwt.auth`): Middleware Bearer token.
- `App\Http\Middleware\RequireRole` (`role`): Middleware de autorización con jerarquía RBAC.
- `App\Http\Middleware\RequirePermission` (`permission`): Middleware para control granular de permisos.
- `App\Services\Auth\JwtService`: Criptografía RFC 7519 HMAC-SHA256.
- `App\Services\Auth\AuthService`: Hashing seguro de contraseñas y expedición de tokens.

### 2.3 Modelos de Persistencia Activos
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
| `ISSUE-F03-T04` | Script de inicialización para creación del usuario administrador inicial | Pendiente |
