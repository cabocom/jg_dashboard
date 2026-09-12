# Análisis Técnico: [GLOBAL-05] Falta de Propagación de Correlation-ID y Trazabilidad Distribuida

## Metadatos
- **Issue ID:** [jg_agente#56](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/56) / [jg_dashboard#46](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/46)
- **Severidad:** 🟡 MEDIO
- **Componentes Afectados:**
  - `jg_dashboard`: `AgentHttpClient.php`, `ExecutionController.php`, `AuditEvent.php`, suites de test.
  - `jg_agente`: `app/core/correlation.py` (nuevo), `app/main.py`, `app/models/io.py`, `app/models/state.py`, `app/security/audit.py`, `app/api/executions.py`, suites de test.
- **Fecha:** Septiembre 2026

---

## 1. Descripción del Problema

En arquitecturas distribuidas compuestas por un Control Plane (Dashboard en Laravel/PHP) y uno o varios Execution Planes (Agentes cognitivos en FastAPI/Python), la trazabilidad distribuida extremo a extremo (*distributed tracing*) es un requisito indispensable para la observabilidad, la depuración y la auditoría de seguridad forense.

### Causa Raíz Detectada:
1. **Pérdida en el Dashboard:** `AgentHttpClient` generaba o adjuntaba la cabecera HTTP `X-Correlation-ID` en las peticiones al agente, pero dicho identificador no se asociaba al modelo de auditoría local (`AuditEvent`), ni se guardaba de forma consistente tras la generación en el cliente. Además, `ExecutionController` no extraía la cabecera recibida desde el frontend/API ni la integraba de forma explícita en el payload de `agentInput` o en los metadatos.
2. **Ausencia de Recepción y Contexto en el Agente:** `jg_agente` no disponía de un middleware ni de mecanismos basados en `contextvars` para capturar la cabecera `X-Correlation-ID`. Como consecuencia:
   - Los registros de logs estándar de Python no incluían el Correlation ID.
   - Las auditorías de seguridad generadas por `SecurityAuditLogger` (`audit_trail`) carecían de trazabilidad respecto al evento originado en el Dashboard.
   - El estado del grafo cognitivo de LangGraph (`AgentState`) y los snapshots de checkpoints no almacenaban el `correlation_id`, impidiendo asociar un thread o paso cognitivo específico a la petición HTTP inicial.
   - Las respuestas devueltas por el agente (`AgentOutput`) no devolvían el `correlation_id` para cerrar el ciclo de trazabilidad.

---

## 2. Impacto Técnico y Operativo

1. **Ruptura de la Línea Forense:** Imposibilidad de correlacionar un incidente de seguridad registrado en `jg_agente` (ej. rechazo de herramienta por política, estímulo malicioso, escalada de privilegios) con la sesión de usuario, IP o evento de trigger registrado en `jg_dashboard`.
2. **Dificultad de Troubleshooting:** En escenarios con múltiples ejecuciones simultáneas o distribuidas (ej. DevOps vs Conversacional), los logs de FastAPI y LangGraph aparecían intercalados sin una clave única que permita filtrar la traza completa de un hilo de ejecución.
3. **Inconsistencias en Respuestas de Control Plane:** El frontend o las integraciones externas recibían respuestas sin confirmación explícita del ID de correlación asociado.

---

## 3. Solución Arquitectónica Implementada

### En `jg_agente` (Execution Plane):
1. **Módulo de Correlación (`app/core/correlation.py`):**
   - Implementación de variable de contexto asíncrona segura con `ContextVar`: `get_correlation_id()`, `set_correlation_id()`, `reset_correlation_id()`.
   - `CorrelationLogFilter(logging.Filter)`: Inyecta dinámicamente `[cid: <id>]` en cada línea de log del sistema formateada con `%(correlation_id)s`.
   - `CorrelationIdMiddleware(BaseHTTPMiddleware)`: Extrae `X-Correlation-ID` de las cabeceras HTTP entrantes o genera un nuevo UUIDv4 canónico. Lo asigna a la variable de contexto, a `request.state.correlation_id` y lo inyecta en la cabecera de respuesta HTTP.
2. **Propagación en Modelos de Entrada/Salida (`app/models/io.py`):**
   - Atributo `correlation_id: str | None = None` añadido a `AgentInput`, `AgentOutput` y `ExecutionThreadDetail`.
3. **Propagación en Estado Cognitivo y Auditoría (`app/models/state.py` & `app/security/audit.py`):**
   - Atributo `correlation_id` añadido a `AgentState` y `AuditRecord`.
   - Métodos de `SecurityAuditLogger` actualizados (`record`, `record_stimulus`, `record_tool_execution`, `record_state_transition`, `record_approval`, `record_event`, `record_audit_record`) para resolver automáticamente el `correlation_id` del contexto actual y persistirlo en el diccionario de detalles JSON de la BD.
4. **Orquestación en `app/api/executions.py`:**
   - Inyección del `correlation_id` en la configuración configurable de LangGraph (`config["configurable"]["correlation_id"]`), en el estado inicial del grafo y en los objetos de respuesta síncrona y asíncrona.

### En `jg_dashboard` (Control Plane):
1. **Preservación y Getter en `AgentHttpClient.php`:**
   - Si no se especifica un ID de correlación al invocar el cliente, se autogenera un UUIDv4 y se almacena en la propiedad de instancia `$this->correlationId`.
   - Se añade el método `getCorrelationId(): ?string`.
2. **Extracción y Trazabilidad en `ExecutionController.php`:**
   - Extracción de la cabecera `X-Correlation-ID` entrante o generación de UUIDv4.
   - Configuración en el cliente HTTP (`$client->withCorrelationId($correlationId)`).
   - Inclusión en `agentInput['correlation_id']` y en `agentInput['metadata']['correlation_id']`.
   - Registro en el payload de `AuditEvent::record(...)` con la clave `'correlation_id' => $correlationId`.
   - Devolución del `correlation_id` en el cuerpo JSON y en la cabecera HTTP `X-Correlation-ID` tanto en respuestas exitosas (200/202) como de error (502/503/504).
   - Exposición en el endpoint de inspección `GET /executions/{agent_id}/{thread_id}` (`show`).
