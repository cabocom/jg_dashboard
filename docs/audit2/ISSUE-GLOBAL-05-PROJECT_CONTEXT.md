# Project Context: [GLOBAL-05] Falta de Propagación de Correlation-ID y Trazabilidad Distribuida

## Componentes y Capas Afectadas

- **Control Plane (`jg_dashboard`):**
  - `app/Services/Agents/AgentHttpClient.php`: Cliente HTTP firmado con generación, persistencia en instancia y getter `getCorrelationId()`.
  - `app/Http/Controllers/Api/ExecutionController.php`: Extracción de cabecera `X-Correlation-ID`, inyección en `AgentInput`, registro en `AuditEvent` y respuesta HTTP con cabecera y body unificados.
  - `app/Models/AuditEvent.php`: Almacena el `correlation_id` en el campo `payload_json`.
- **Execution Plane (`jg_agente`):**
  - `app/core/correlation.py`: Módulo central de trazabilidad (`ContextVar`, `CorrelationLogFilter`, `CorrelationIdMiddleware`).
  - `app/main.py`: Registro global del middleware de correlación y configuración de filtros en los loggers de la aplicación.
  - `app/models/io.py`: Campos `correlation_id` en `AgentInput`, `AgentOutput` y `ExecutionThreadDetail`.
  - `app/models/state.py`: Campo `correlation_id` en `AgentState` y `AuditRecord`.
  - `app/security/audit.py`: Resolución automática y persistencia de `correlation_id` en `details["correlation_id"]`.
  - `app/api/executions.py`: Propagación hacia LangGraph (`config` y `state`) y retorno al Control Plane.

---

## Directrices de Desarrollo y Gobernanza

1. **Inyección Obligatoria de X-Correlation-ID:**
   - Toda llamada HTTP entre el Control Plane y el Execution Plane debe portar la cabecera `X-Correlation-ID`. Si no viene dada por el cliente iniciador, el Control Plane debe generarla inmediatamente antes de invocar cualquier endpoint.
2. **Uso de ContextVars en Python:**
   - En `jg_agente`, cualquier procesamiento asíncrono o tarea en segundo plano debe respetar la variable de contexto de correlación. Se debe usar `get_correlation_id()` para asociar logs y trazas de auditoría.
3. **Formato de Logs Estándar:**
   - Los logs de consola y archivo en `jg_agente` incorporan el prefijo `%(correlation_id)s` (renderizado como `[cid: <uuid>]` o `[cid: none]`).
4. **Registro Cruzado de Auditoría:**
   - Al investigar incidentes o fallos en producción, el `correlation_id` registrado en `audit_events` del Dashboard es idéntico al `correlation_id` registrado en los detalles de `CoreAuditRecord` y en las trazas del agente.
