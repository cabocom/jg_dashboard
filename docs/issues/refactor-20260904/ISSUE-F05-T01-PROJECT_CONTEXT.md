# Contexto de Proyecto: jg_dashboard (Post-Fase 05 / ISSUE-F05-T01)

**Versión del documento:** 3.5.0  
**Fecha de actualización:** 5 de septiembre de 2026  
**Componente:** Control Plane (`jg_dashboard`)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  
**Issue Referenciado:** `ISSUE-F05-T01` — Endpoint de recepción de heartbeats: POST /api/v1/agents/heartbeat  

---

## 1. Estado del Proyecto tras la Finalización de ISSUE-F05-T01

- **Fases 01 a 04 (100% Completadas):**
  - Tooling, Docker y CI/CD.
  - Modelos Eloquent y migraciones para usuarios, agentes, tokens de bootstrap, auditoría y solicitudes HITL.
  - Autenticación JWT y roles RBAC (`ADMIN`, `OPERATOR`, `VIEWER`).
  - Sistema de registro de agentes con bootstrap tokens (TTL 24h, un solo uso) y emisión de credencial permanente.
- **Fase 05 (Iniciada):**
  - `ISSUE-F05-T01`: Endpoint de recepción de heartbeats periódicos `POST /api/v1/agents/heartbeat` autenticado mediante `permanent_agent_secret`, actualizando `last_heartbeat_at`, confirmando estado `ACTIVE` y persistiendo métricas operativas.
- **Métricas de Calidad:** 114 tests pasando en Pest PHP (521 aserciones).
