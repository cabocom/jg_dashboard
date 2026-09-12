## Resolución y Cierre de Issue: [GLOBAL-05] Falta de Propagación de Correlation-ID y Trazabilidad Distribuida

Se ha resuelto de forma completa y robusta la trazabilidad distribuida entre el Control Plane (`jg_dashboard`) y el Execution Plane (`jg_agente`):

### Resumen de Cambios Implementados:

1. **Gestión y Propagación en el Dashboard (`jg_dashboard`):**
   - En `AgentHttpClient.php`, se preserva el `correlation_id` generado en la instancia del cliente y se expone a través de `getCorrelationId(): ?string`.
   - En `ExecutionController.php`, se captura la cabecera `X-Correlation-ID` entrante (o se genera un UUIDv4), se inyecta en el cliente HTTP, en el payload de `AgentInput` y en sus metadatos.
   - Se registra el `correlation_id` en el log de auditoría del Dashboard (`AuditEvent`) dentro de `payload_json`.
   - Se retorna el `correlation_id` tanto en el cuerpo JSON de respuesta como en la cabecera HTTP `X-Correlation-ID` en todos los flujos (éxito 200, asíncrono 202, error de pasarela 502/503/504 y consulta de hilos en `show`).

2. **Captura de Contexto y Auditoría en el Agente (`jg_agente`):**
   - Se implementó `app/core/correlation.py` con `ContextVar` asíncrono, filtro de logging `CorrelationLogFilter` y middleware HTTP `CorrelationIdMiddleware`.
   - Se actualizó el formato de logging para inyectar automáticamente el prefijo `[cid: <uuid>]` en cada línea de log.
   - Se incorporó `correlation_id` en los esquemas Pydantic `AgentInput`, `AgentOutput`, `ExecutionThreadDetail`, `AgentState` y `AuditRecord`.
   - Se adaptó `SecurityAuditLogger` para inyectar automáticamente el `correlation_id` activo en todos los eventos de auditoría persistidos en base de datos (`CoreAuditRecord.details`).
   - Se conectó la propagación hacia el grafo cognitivo de LangGraph (`config["configurable"]["correlation_id"]` e `initial_state`).

3. **Verificación y Pruebas:**
   - Nuevos tests en `jg_agente/tests/test_correlation.py` (5 tests de ciclo de vida de contexto, filtros, middleware y auditoría).
   - Nuevo test en `jg_dashboard/tests/Feature/ExecutionTriggerApiTest.php` validando la propagación y auditoría de `X-Correlation-ID`.
   - Suite completa de `jg_agente` aprobada: **366 passed**, Ruff y MyPy con 0 errores en 106 archivos.
   - Suite completa de `jg_dashboard` aprobada: **255 passed (1222 assertions)**, Pint y PHPStan limpios.
   - Script `./check.sh` ejecutado con éxito total.

---
**Commits Asociados:**
- `feat(tracing): propagar correlation-id y trazabilidad distribuida e2e (GLOBAL-05)`
