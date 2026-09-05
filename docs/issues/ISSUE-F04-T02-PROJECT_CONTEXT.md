# Contexto de Proyecto: jg_dashboard (Post-Fase 04 / ISSUE-F04-T02)

**Versión del documento:** 3.2.0  
**Fecha de actualización:** 5 de septiembre de 2026  
**Componente:** Control Plane (`jg_dashboard`)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  
**Issue Referenciado:** `ISSUE-F04-T02` — Endpoint administrativo: POST /api/v1/admin/agents  

---

## 1. Estado del Proyecto tras la Finalización de ISSUE-F04-T02

- **Fase 01 (100%):** Arquitectura base, configuración de entorno, Dockerfile y Compose con PostgreSQL propio, CI/CD en GitLab.
- **Fase 02 (100%):** Modelos Eloquent y migraciones de PostgreSQL (`User`, `Agent`, `BootstrapToken`, `ApprovalRequest`, `AuditEvent`).
- **Fase 03 (100%):** Autenticación JWT (`JwtService`, `AuthService`), autorización RBAC (`jwt.auth`, `role`, `permission`), endpoints de sesión (`/api/v1/auth/login`, `/api/v1/auth/me`, `/api/v1/auth/logout`) y comando de inicialización de administrador (`php artisan dashboard:init-admin`).
- **Fase 04 (Avanzada):**
  - `ISSUE-F04-T01`: Servicio de registro de agentes y generación de bootstrap tokens con caducidad estricta de 24h y almacenamiento seguro Zero-Trust.
  - `ISSUE-F04-T02`: Endpoint administrativo `POST /api/v1/admin/agents` protegido con `role:admin`, devolviendo respuesta `201 Created` con `agent_id` y `bootstrap_token` para el aprovisionamiento de agentes.
- **Métricas de Calidad:** 87 tests pasando en Pest PHP (417 aserciones).
