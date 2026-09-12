# Walkthrough: [GLOBAL-05] Falta de Propagación de Correlation-ID y Trazabilidad Distribuida

## Resumen de Cambios

Se ha completado la implementación de la trazabilidad distribuida de extremo a extremo mediante el uso coordinado de `X-Correlation-ID` entre `jg_dashboard` (Control Plane) y `jg_agente` (Execution Plane).

---

### 1. Cambios en `jg_agente` (Python / FastAPI)

- **`app/core/correlation.py`:**
  - Se implementó la variable de contexto asíncrona segura (`ContextVar`) para almacenar el correlation ID por corutina/request.
  - Se creó el `CorrelationLogFilter` para inyectar automáticamente el ID en los logs de Python (`[cid: <id>]`).
  - Se creó el `CorrelationIdMiddleware` para extraer la cabecera `X-Correlation-ID` o generar un UUID canónico, asignándolo tanto a la variable de contexto como al encabezado de la respuesta HTTP.
- **`app/main.py`:**
  - Se registró el middleware `CorrelationIdMiddleware` al inicio del stack de middlewares.
  - Se aplicó el filtro `CorrelationLogFilter` y el formateador correspondiente a los manejadores de logging.
- **`app/models/io.py` & `app/models/state.py`:**
  - Se agregaron campos `correlation_id` opcionales a `AgentInput`, `AgentOutput`, `ExecutionThreadDetail`, `AuditRecord` y `AgentState`.
- **`app/security/audit.py`:**
  - Se adaptaron todas las funciones de `SecurityAuditLogger` para consultar el ID de correlación activo y persistirlo en `details["correlation_id"]`.
- **`app/api/executions.py`:**
  - Se inyecta el `correlation_id` en la invocación de LangGraph (`config` y `state`), y se devuelve en el `AgentOutput`.
- **`tests/test_correlation.py`:**
  - Suite de tests dedicada que verifica el ciclo de vida de la variable de contexto, el filtrado de logs, el middleware HTTP y la persistencia en el log de auditoría.

---

### 2. Cambios en `jg_dashboard` (PHP / Laravel)

- **`app/Services/Agents/AgentHttpClient.php`:**
  - Se añadió el método `getCorrelationId(): ?string`.
  - Se actualizó `generateSignatureHeaders()` para almacenar el UUID generado en la propiedad `$this->correlationId` en caso de que no se hubiera provisto uno previamente.
- **`app/Http/Controllers/Api/ExecutionController.php`:**
  - Extracción de `X-Correlation-ID` de la petición HTTP entrante (o generación de UUID).
  - Inyección en el cliente (`$client->withCorrelationId($correlationId)`), en `AgentInput` y en `AgentInput['metadata']`.
  - Registro explícito en `AuditEvent::record(...)` dentro de `payload_json['correlation_id']`.
  - Inclusión de `correlation_id` en las respuestas JSON y en la cabecera `X-Correlation-ID` (para 200, 202 y errores 50x).
  - Exposición de `correlation_id` en el endpoint de detalle `GET /executions/{agent_id}/{thread_id}`.
- **`tests/Feature/ExecutionTriggerApiTest.php`:**
  - Nueva prueba que valida la propagación de `X-Correlation-ID` desde el request entrante hacia el agente, la cabecera de respuesta y el registro de `AuditEvent`.

---

## Verificación

1. **Python (`jg_agente`):**
   - Ruff: 106 archivos formateados sin errores.
   - MyPy: 0 errores de tipado estricto en 106 archivos.
   - Pytest: 366 tests aprobados en 70s.
2. **PHP (`jg_dashboard`):**
   - Pint: 0 errores de estilo.
   - PHPStan: 0 errores de tipado estático.
   - Pest: 255 tests aprobados (1222 aserciones) en 10s.
3. **Verificación Global (`./check.sh`):**
   - Ambas suites ejecutadas y aprobadas al 100%.
