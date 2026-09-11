# Contexto de Proyecto: [ISSUE-DASH-005] Desacoplamiento y retirada de referencias legacy DevOps60kApiClient

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-005
- **GitLab Work Item:** jg_dashboard#40
- **Fase:** Fase Post-Auditoría (Arquitectura y Desacoplamiento)
- **Prioridad:** Medium
- **Complejidad Estimada:** M
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
Durante la auditoría técnica independiente (FINDINGS.md, DASH-005), se identificó que el sistema aún conservaba acoplamientos residuales hacia el cliente legacy `DevOps60kApiClient` y configuraciones orientadas a un único backend monolítico (`devops60k_app:8000`):
1. **Binding Singleton en `AppServiceProvider`**: Se registraba un singleton obsoleto para `DevOps60kApiClient`, resolviendo credenciales y URL fijas del backend legacy.
2. **Filament Widgets y Páginas**:
   - `AgentHealthWidget`: Inyectaba `DevOps60kApiClient` y consultaba `/health` del host legacy, ignorando la entidad `Agent` de la base de datos y la capacidad de monitorizar múltiples agentes.
   - `TopologyMap`: En su método `refreshTopology`, invocaba `$apiClient->checkHealth()` legacy y mantenía dependencias estáticas.
   - `AgentTaskResource`: Las acciones de Filament (`trigger`, `approve_hitl`, `reject_hitl`) inyectaban `DevOps60kApiClient` en lugar de utilizar `AgentHttpClient` y el modelo de agente correspondiente.

Para resolver este acoplamiento:
- **Retirada del Singleton**: Se eliminó el binding singleton de `DevOps60kApiClient` de `AppServiceProvider`.
- **Migración a `AgentHttpClient`**: Se actualizaron `AgentHealthWidget`, `TopologyMap` y `AgentTaskResource` para interactuar con los agentes mediante `AgentHttpClient`, garantizando autenticación segura HMAC-SHA256 (`X-Signature`, `X-Timestamp`, `X-Agent-ID`).
- **Resolución Basada en Modelos Eloquent**: Los widgets y acciones resuelven los agentes a través del modelo Eloquent `Agent` (evaluando `Agent::active()->first()` o `Agent::all()`), admitiendo topologías multi-agente y la comprobación de salud en tiempo real (`isHealthy(90)`).

## 3. Criterios de Aceptación
- El binding de `DevOps60kApiClient` en `AppServiceProvider` queda completamente eliminado.
- `AgentHealthWidget` utiliza `AgentHttpClient` y el modelo `Agent`, informando el estado y métricas de salud reales.
- `TopologyMap` no referencia a `DevOps60kApiClient` y genera la topología apoyándose en la colección de agentes Eloquent y `AgentHttpClient`.
- `AgentTaskResource` ejecuta el disparo y la resolución HITL mediante `AgentHttpClient` contra el agente activo asignado.
- La suite completa de pruebas de Pest en `jg_dashboard` supera todos los tests sin regresiones.
