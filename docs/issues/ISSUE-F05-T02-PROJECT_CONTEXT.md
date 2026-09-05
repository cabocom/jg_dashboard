# Contexto de Proyecto: jg_dashboard (Post-Fase 05 / ISSUE-F05-T02)

**Versión del documento:** 3.6.0  
**Fecha de actualización:** 5 de septiembre de 2026  
**Componente:** Control Plane (`jg_dashboard`)  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  
**Issue Referenciado:** `ISSUE-F05-T02` — Servicio en background de detección de agentes caídos (Health Monitor)  

---

## 1. Estado del Proyecto tras la Finalización de ISSUE-F05-T02

- **Fases 01 a 04 (100% Completadas):**
  - Tooling, Docker y CI/CD.
  - Modelos Eloquent y migraciones para usuarios, agentes, tokens de bootstrap, auditoría y solicitudes HITL.
  - Autenticación JWT y roles RBAC (`ADMIN`, `OPERATOR`, `VIEWER`).
  - Sistema de registro de agentes con bootstrap tokens y emisión de credencial permanente.
- **Fase 05 (Avanzada al 66%):**
  - `ISSUE-F05-T01`: Endpoint de recepción de heartbeats periódicos `POST /api/v1/agents/heartbeat` autenticado mediante `permanent_agent_secret`.
  - `ISSUE-F05-T02`: Servicio en segundo plano `AgentHealthMonitorService`, comando Artisan `agents:check-health` y programación cada minuto para identificar agentes inactivos (>90s sin reporte) y transicionarlos de forma automática a estado `OFFLINE`.
- **Métricas de Calidad:** 119 tests pasando en Pest PHP (541 aserciones).
