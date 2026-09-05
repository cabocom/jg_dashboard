# Contexto de Proyecto: jg_dashboard (Post-Fase 04 / ISSUE-F04-T04)

**Versión del documento:** 3.4.0  
**Fecha de actualización:** 5 de septiembre de 2026  
**Componente:** Control Plane (`jg_dashboard`)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  
**Issue Referenciado:** `ISSUE-F04-T04` — Validación estricta de caducidad (TTL 24h) y revocación de tokens  

---

## 1. Estado del Proyecto tras la Finalización de la FASE 04

- **Fase 01 (100%):** Tooling base, Dockerfile multi-stage, Docker Compose con PostgreSQL propio (puerto 5434) y CI/CD en GitLab.
- **Fase 02 (100%):** Modelos Eloquent y migraciones (`User`, `Agent`, `BootstrapToken`, `ApprovalRequest`, `AuditEvent`).
- **Fase 03 (100%):** Autenticación JWT (`JwtService`, `AuthService`), autorización RBAC (`jwt.auth`, `role`, `permission`), endpoints de sesión y comando `dashboard:init-admin`.
- **Fase 04 (100% Completada):**
  - `ISSUE-F04-T01`: Servicio criptográfico de registro de agentes y generación de bootstrap tokens con TTL 24h.
  - `ISSUE-F04-T02`: Endpoint administrativo `POST /api/v1/admin/agents` (código 201 Created).
  - `ISSUE-F04-T03`: Endpoint de consumo `POST /api/v1/agents/register` para activación y emisión de credencial permanente `sec_*`.
  - `ISSUE-F04-T04`: Validación estricta de caducidad con límite absoluto de 24h, garantía de uso único mediante bloqueo por fila, revocación administrativa y respuesta `401 Unauthorized`.
- **Métricas de Calidad:** 102 tests pasando en Pest PHP (488 aserciones).
