# Análisis Técnico: [ISSUE-F06-T02] Pasarela para disparar ejecuciones: POST /api/v1/executions/trigger

**Issue ID:** ISSUE-F06-T02  
**Fase:** FASE 06 (Cliente HTTP proxy y orquestación de ejecuciones hacia jg_agente)  
**Prioridad:** Critical  
**Complejidad:** M  
**Dependencias:** ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

En cumplimiento con el principio arquitectónico de desacoplamiento y único punto de exposición externa, los agentes `jg_agente` residen en redes privadas sin acceso directo desde clientes externos ni operadores humanos. `jg_dashboard` actúa como pasarela (Gateway y Control Plane) responsable de:
1. Autenticar y autorizar a los usuarios solicitantes mediante RBAC (`admin` y `operator`).
2. Resolver el agente objetivo (o seleccionar el agente activo predeterminado).
3. Normalizar la solicitud hacia el contrato formal `AgentInput`, inyectando contexto de seguridad (`user_context`) y metadatos de auditoría.
4. Delegar la ejecución a través del cliente firmado `AgentHttpClient` (`POST /executions/trigger`).
5. Registrar el evento en la bitácora de auditoría (`dashboard_audit_events`).
6. Si la ejecución queda detenida en espera de intervención humana (HITL), persistir preventivamente la solicitud en `dashboard_approval_requests`.
7. Devolver una respuesta estructurada al cliente (200 OK para flujos síncronos, 202 Accepted para asíncronos) manejando caídas de upstream con códigos 502/503/504.

---

## 2. Arquitectura de la Solución

### 2.1 Endpoint y Seguridad
- Rutas: `POST /api/v1/executions/trigger` y alias `POST /api/executions/trigger`.
- Middlewares: `jwt.auth` y `role:operator`.
  - Roles autorizados: `operator` y `admin` (mediante jerarquía de roles).
  - Rechazos: `viewer` recibe `403 Forbidden`, peticiones sin token reciben `401 Unauthorized`.

### 2.2 Validación y Resolución de Agente
- Parámetros de entrada:
  - `prompt` / `user_prompt`: Obligatorio, cadena de texto no vacía.
  - `agent_id`: Opcional. Si se provee, debe existir en `dashboard_agents` y su estado debe ser `ACTIVE`. Si no está activo, se responde `400 Bad Request`. Si no se provee, se resuelve el primer agente activo registrado o el cliente configurado por defecto.
  - `thread_id`: Opcional. Si se omite, se genera un UUIDv4 automáticamente para mantener el estado conversacional.
  - `parameters`, `async`, `metadata`: Opcionales.

### 2.3 Normalización hacia `AgentInput`
El payload enviado a `jg_agente` se estructura estrictamente según el esquema formal de Pydantic:
```json
{
  "execution_id": "uuid-v4",
  "thread_id": "uuid-v4",
  "prompt": "instrucción",
  "user_prompt": "instrucción",
  "parameters": { "async": false },
  "user_context": {
    "user_id": "1",
    "username": "Operador",
    "email": "operador@dominio.com",
    "role": "operator",
    "permissions": ["tasks:trigger", "hitl:approve", ...]
  },
  "metadata": {
    "triggered_by": "operador@dominio.com",
    "user_role": "operator",
    "client_ip": "127.0.0.1",
    "agent_id": "uuid-agente"
  }
}
```

### 2.4 Auditoría y Gobernanza HITL
- `AuditEvent::record()` registra la acción con IP, agente, usuario y códigos de retorno.
- Si `response.data.requires_approval === true` o el estado es `waiting_approval`:
  Se crea una fila en `dashboard_approval_requests` con el plan propuesto, comandos de compensación y nivel de riesgo evaluado.

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard envía `AgentInput` a `jg_agente` con formato canónico y contexto de usuario.
- [x] Recibe la respuesta de inicio o ejecución completada y retorna el ID de ejecución (`execution_id`) y estado al cliente.
- [x] Restricción RBAC: sólo `operator` y `admin` pueden disparar ejecuciones; `viewer` es bloqueado con 403.
- [x] Validación estricta del agente: agentes inexistentes (404) o no activos (400) son rechazados.
- [x] Persistencia automática en `dashboard_audit_events` y `dashboard_approval_requests` (HITL).
- [x] Manejo controlado de caídas de red y errores de upstream retornando códigos 502/503/504.
