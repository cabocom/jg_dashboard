# Contexto de Proyecto: jg_dashboard (Post-Fase 05 / ISSUE-F05-T03)

**Versión del documento:** 3.7.0  
**Fecha de actualización:** 5 de septiembre de 2026  
**Componente:** Control Plane (`jg_dashboard`)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  
**Issue Referenciado:** `ISSUE-F05-T03` — Endpoint de listado y estado de agentes: GET /api/v1/agents  

---

## 1. Estado del Proyecto tras la Finalización de la FASE 05

- **Fases 01 a 05 (100% Completadas):**
  - **Fase 01 (Tooling):** Docker multi-stage, PostgreSQL propio aislado en puerto 5434, scripts y CI/CD en GitLab.
  - **Fase 02 (Persistencia):** Modelos Eloquent y migraciones para usuarios, agentes, tokens, auditoría y solicitudes HITL.
  - **Fase 03 (Auth & RBAC):** JWT HMAC-SHA256, roles (`ADMIN`, `OPERATOR`, `VIEWER`), middlewares y comando `dashboard:init-admin`.
  - **Fase 04 (Aprovisionamiento Zero-Trust):** Tokens de bootstrap (24h de validez, uso único), endpoints administrativos y de intercambio por credencial permanente `sec_*`.
  - **Fase 05 (Monitoreo y Telemetría):** Endpoint de heartbeats periódicos cada 30s (`POST /api/v1/agents/heartbeat`), monitor de caídas en segundo plano (`agents:check-health`, timeout 90s) y endpoint de observabilidad y listado paginado (`GET /api/v1/agents`).
- **Métricas de Calidad:** 127 tests pasando en Pest PHP (608 aserciones).
