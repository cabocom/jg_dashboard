# Análisis Técnico: [ISSUE-F06-T03] Consulta de estado de ejecuciones: GET /api/v1/executions/{agent_id}/{thread_id}

**Issue ID:** ISSUE-F06-T03  
**Fase:** FASE 06 (Cliente HTTP proxy y orquestación de ejecuciones hacia jg_agente)  
**Prioridad:** High  
**Complejidad:** S  
**Dependencias:** ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

Un principio medular de la arquitectura de jG es el **Aislamiento Total de Persistencia (No Direct DB Access)**: el Dashboard tiene terminantemente prohibido conectarse a la base de datos PostgreSQL de los agentes para leer checkpoints o estados del grafo LangGraph.

Para que los usuarios administradores, operadores u observadores puedan consultar el progreso, historial cronológico de pasos y estado de interrupción HITL de una tarea activa o finalizada, `jg_dashboard` debe actuar como proxy inteligente:
1. Recibir la solicitud `GET /api/v1/executions/{agent_id}/{thread_id}` protegida por JWT.
2. Resolver el agente en la base de datos del Dashboard (`dashboard_agents`), verificando su existencia y que haya completado su registro.
3. Invocar vía HTTP firmado el endpoint `GET /executions/{thread_id}` en `jg_agente` mediante `AgentHttpClient`.
4. Unificar el resultado devuelto por el checkpointer del agente con el estado de gobernanza HITL local (`dashboard_approval_requests`).
5. Retornar una respuesta estructurada al cliente sin exponer secretos ni violar límites de persistencia.

---

## 2. Arquitectura de la Solución

### 2.1 Endpoint y Control de Accesos
- **Ruta Principal:** `GET /api/v1/executions/{agent_id}/{thread_id}`
- **Ruta Alias:** `GET /api/executions/{agent_id}/{thread_id}`
- **Seguridad:** Middleware `jwt.auth`. Accesible para roles `admin`, `operator` y `viewer`, permitiendo auditoría y visualización del estado operacional por cualquier usuario autenticado.

### 2.2 Validación y Resolución
- Si `agent_id` no existe en `dashboard_agents`, se retorna `404 Not Found`.
- Si el agente está en estado `PENDING` (registro no consumido), se retorna `400 Bad Request` indicando que el agente no está listo.
- Se instancia `AgentHttpClient::forAgent($agent)` para dirigir la petición a la URL interna del agente con firmado HMAC-SHA256 y cabeceras de correlación.

### 2.3 Respuesta Unificada y Enriquecida
- Si `jg_agente` retorna 404 (el thread no existe en el checkpointer), se responde `404 Not Found`.
- Si `jg_agente` es inaccesible o responde con error de servidor, se retorna `502 Bad Gateway` o `503 Service Unavailable`.
- En respuesta exitosa (200 OK), se cruza el `thread_id` con `ApprovalRequest` en la base de datos local para incorporar metadatos de gobernanza (`hitl_governance: { approval_id, status, risk_level, justification, resolved_at }`).

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard consulta `GET /executions/{thread_id}` en el agente mediante cliente HTTP firmado.
- [x] Retorna estado unificado con pasos, progreso, outputs y pausas HITL.
- [x] Cero acceso a la base de datos de persistencia del agente.
- [x] Accesible por todos los roles autenticados (`viewer`, `operator`, `admin`), y protegido contra usuarios anónimos (401).
- [x] Manejo controlado de fallos de red y agentes caídos o no registrados.
