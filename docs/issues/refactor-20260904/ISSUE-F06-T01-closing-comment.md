Se ha completado la implementación de **[ISSUE-F06-T01] Cliente HTTP interno para comunicación con jg_agente**.

### Resumen de Implementación
1. **Configuración Centralizada:** Creación de `config/agent.php` con variables de entorno (`JG_AGENTE_API_URL`, `JG_AGENTE_SECRET`, `AGENT_HTTP_TIMEOUT`, `AGENT_HTTP_RETRY_TIMES`, `AGENT_HTTP_RETRY_SLEEP_MS`).
2. **Jerarquía de Excepciones:** Definición de `AgentHttpException`, `AgentConnectionException` y `AgentTimeoutException` en `app/Exceptions/`.
3. **Servicio Cliente HTTP (`AgentHttpClient`):**
   - Firmado criptográfico con HMAC-SHA256 (`X-Control-Plane`, `X-Control-Plane-Secret`, `X-Timestamp`, `X-Signature`, `X-Correlation-ID`, `X-Agent-ID`).
   - Métodos tipados para todos los endpoints del Execution Plane: `checkHealth()`, `getCapabilities()`, `triggerExecution()`, `getExecutionStatus()`, `approveExecution()`, `rejectExecution()`, `getMetrics()`.
   - Soporte para factory por modelo Eloquent: `AgentHttpClient::forAgent(Agent $agent)` para resolución dinámica de endpoints.
   - Manejo de reintentos con backoff en errores 5xx y control estructurado de timeouts y errores de red.
4. **Verificación Automatizada:** Suite unitaria completa en `tests/Unit/AgentHttpClientTest.php` con mocks de `Http::fake()`.
5. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.8.0) y documentación técnica en `docs/issues/ISSUE-F06-T01-*`.

/close
