### Implementación y Verificación del Endpoint de Heartbeats `POST /api/v1/agents/heartbeat` (`ISSUE-F05-T01`)

Se ha implementado el endpoint de recepción y procesamiento periódico de telemetría y señales de vida en `jg_dashboard`:

1. **Autenticación Segura y Procesamiento de Heartbeats:**
   - Implementado `AgentHeartbeatService` y el controlador `AgentHeartbeatController@heartbeat`.
   - Autenticación criptográfica con `permanent_agent_secret` (comparación en tiempo constante SHA-256) contra `dashboard_agents.permanent_secret_hash`.
   - Admite `X-Agent-ID` y `Authorization: Bearer <secret>` en cabeceras o en el cuerpo JSON de la petición.
   - Actualiza de forma atómica el timestamp `last_heartbeat_at`, ratifica el estado en `ACTIVE` y almacena las métricas de rendimiento en `metrics_json`.
   - Retorna código `200 OK` en caso de éxito, o `401 Unauthorized` si faltan credenciales, el agente no existe, está en estado pendiente o el secreto no coincide.

2. **Verificación Automatizada:**
   - 5 nuevas pruebas unitarias en `AgentHeartbeatServiceTest`.
   - 7 nuevas pruebas de feature en `AgentHeartbeatApiTest`.
   - Total de pruebas en `jg_dashboard`: **114 tests pasando (521 aserciones)** al 100%.

/close
