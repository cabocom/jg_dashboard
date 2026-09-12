# Análisis Técnico: [ISSUE-F07-T04] Endpoint de rechazo HITL: POST /api/v1/approvals/{approval_id}/reject

**Issue ID:** ISSUE-F07-T04  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Prioridad:** Critical  
**Complejidad:** M  
**Dependencias:** ISSUE-F07-T01, ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

En el marco de la gobernanza Human-In-The-Loop (HITL), cuando un operador humano o administrador determina que un plan propuesto por un agente presenta un riesgo inaceptable, comandos destructivos o desviaciones operativas, debe disponer de un mecanismo explícito para rechazar la solicitud.

A diferencia del flujo de aprobación, el rechazo exige obligatoriamente un motivo justificado (`justification`) que quede indexado en el registro forense de auditoría. El objetivo de este issue es:
1. Validar la identidad y rol del usuario (`operator` / `admin`).
2. Exigir la justificación del rechazo (`min: 5 caracteres`).
3. Verificar que la solicitud se encuentre en estado `PENDING` (404 si no existe, 409 si ya está resuelta).
4. Conectar con el agente mediante `AgentHttpClient` invocando `POST /executions/{thread_id}/reject` con cabeceras firmadas HMAC-SHA256.
5. Transicionar el estado a `REJECTED`, persistir el operador y la justificación, y emitir el evento `hitl.rejected` en `dashboard_audit_events`.
6. En caso de caída de red o indisponibilidad del agente, preservar el estado `PENDING` para permitir reintentos.

---

## 2. Arquitectura de la Solución

### 2.1 Seguridad y Autorización
- **Ruta Principal:** `POST /api/v1/approvals/{approval_id}/reject`
- **Ruta Alias:** `POST /api/approvals/{approval_id}/reject`
- **Control de Acceso:** Middleware `jwt.auth` y `role:operator`.
  - Roles permitidos: `operator` y `admin` (mediante jerarquía RBAC).
  - Rol `viewer`: Denegado con `403 Forbidden`.
  - Anónimo: `401 Unauthorized`.

### 2.2 Validación Estricta
- El cuerpo de la petición debe contener:
  ```json
  {
    "justification": "Motivo detallado por el cual se rechaza y cancela el plan"
  }
  ```
- Si `justification` no está presente o tiene menos de 5 caracteres, se responde con `422 Unprocessable Entity`.

### 2.3 Capa de Servicio: `HitlApprovalService::rejectApproval`
- Búsqueda de la solicitud y comprobación de precondiciones (`isPending()`, estado del agente).
- Delegación a `AgentHttpClient::forAgent($agent)->rejectExecution($approval->thread_id, $payload)`.
- Si el agente confirma la cancelación (200 OK), se ejecuta `$approval->reject($resolver, $justification)`.
- Registro del evento de auditoría `hitl.rejected` vía `AuditEvent::record()`.

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard valida permisos del usuario (`operator` / `admin`).
- [x] Exige justificación obligatoria con validación de longitud mínima.
- [x] Registra el rechazo con su motivo en `dashboard_audit_events` (`hitl.rejected`).
- [x] Llama a `/reject` en el agente cancelando el plan cognitivo de forma remota.
- [x] Maneja rechazos concurrentes con `409 Conflict`.
- [x] Preserva el estado `PENDING` ante fallos de conectividad del agente.
- [x] 100% de pruebas automáticas superadas en `./scripts/check.sh`.
