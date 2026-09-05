# Análisis Técnico: [ISSUE-F07-T03] Endpoint de aprobación HITL: POST /api/v1/approvals/{approval_id}/approve

**Issue ID:** ISSUE-F07-T03  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Prioridad:** Critical  
**Complejidad:** M  
**Dependencias:** ISSUE-F07-T01, ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

Cuando una ejecución de `jg_agente` se detiene en un checkpoint condicional por detección de un plan de alto riesgo (`waiting_approval`), el operador humano o administrador debe tener la capacidad de evaluar las acciones propuestas y conceder su autorización explícita para reanudar el grafo cognitivo.

El objetivo de este issue es proporcionar un endpoint protegido en el Dashboard que:
1. Valide la identidad y permisos RBAC del usuario (`operator` / `admin`).
2. Compruebe la existencia de la solicitud y su estado `PENDING` (evitando re-aprobaciones o inconsistencias con códigos 404 o 409).
3. Conecte de manera segura y firmada con la instancia correspondiente de `jg_agente` mediante `AgentHttpClient` invocando `POST /executions/{thread_id}/approve`.
4. Ante el éxito de la comunicación con el agente, marque la solicitud como `APPROVED`, persista el operador y su justificación, y genere una traza inmutable en `dashboard_audit_events` (`hitl.approved`).
5. En caso de caída de red o fallo en el agente, preserve el estado `PENDING` para permitir reintentos seguros.

---

## 2. Arquitectura de la Solución

### 2.1 Seguridad y Autorización
- **Ruta Principal:** `POST /api/v1/approvals/{approval_id}/approve`
- **Ruta Alias:** `POST /api/approvals/{approval_id}/approve`
- **Autenticación y RBAC:** Protegido con `jwt.auth` y `role:operator`.
  - Autorizado para usuarios con rol `operator` y `admin` (mediante jerarquía RBAC).
  - Denegado con `403 Forbidden` para rol `viewer`.
  - Rechazado con `401 Unauthorized` para peticiones no autenticadas.

### 2.2 Lógica de Negocio en `HitlApprovalService::approveApproval`
- Se recupera la solicitud por su clave primaria (`findApproval`).
- Validación de precondiciones:
  - Existencia de la solicitud (404 Not Found si no existe).
  - Estado `isPending()` (409 Conflict si ya fue resuelta).
  - Existencia y estado del agente asociado (400 Bad Request si el agente no existe o está en estado `PENDING`).
- Delegación externa:
  - Invoca `AgentHttpClient::forAgent($agent)->approveExecution($approval->thread_id, $payload)`.
  - Si el agente responde con error de red o timeout (502/503/504), se retorna el error al cliente sin alterar el estado en base de datos.
- Confirmación y Auditoría:
  - Si el agente reanuda exitosamente (200 OK), se ejecuta `$approval->approve($resolver, $justification)`.
  - Se registra el evento de auditoría `hitl.approved` mediante `AuditEvent::record()`.

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard valida permisos del usuario (`operator` y `admin`).
- [x] Registra evento inmutable de auditoría (`hitl.approved`) con usuario, agente, hilo y nivel de riesgo.
- [x] Llama al endpoint `/approve` en el agente reanudando su ejecución.
- [x] Maneja estados de conflicto (409) cuando la solicitud ya fue resuelta.
- [x] Preserva el estado `PENDING` si la comunicación con el agente falla, evitando inconsistencias.
- [x] 100% de pruebas automáticas superadas en `./scripts/check.sh`.
