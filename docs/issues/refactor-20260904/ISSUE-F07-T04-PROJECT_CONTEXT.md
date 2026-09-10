# Contexto Específico de Issue: ISSUE-F07-T04

**Issue ID:** ISSUE-F07-T04  
**Título:** Endpoint de rechazo HITL: POST /api/v1/approvals/{approval_id}/reject  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Versión de Contexto Global Vinculada:** 3.15.0  

---

## 1. Módulos y Clases Involucradas

- `app/Http/Controllers/Api/HitlApprovalController.php`: Endpoint `reject` con validación de justificación requerida.
- `app/Services/Hitl/HitlApprovalService.php`: Método `rejectApproval` para orquestar la cancelación con `jg_agente` y persistir la auditoría.
- `app/Services/Agents/AgentHttpClient.php`: Cliente HTTP firmado encargado de enviar `POST /executions/{thread_id}/reject`.
- `app/Models/ApprovalRequest.php`: Actualización de estado (`REJECTED`) y justificación.
- `app/Models/AuditEvent.php`: Emisión inmutable del evento `hitl.rejected`.
- `tests/Feature/HitlApprovalRejectApiTest.php`: Pruebas de integración del endpoint de rechazo.

---

## 2. Decisiones Arquitectónicas Aplicadas

1. **Obligatoriedad del Motivo de Rechazo:**
   A diferencia de la aprobación donde la justificación es opcional, el rechazo exige un motivo explícito para evitar cancelaciones accidentales y dotar al equipo de seguridad de trazabilidad sobre por qué se detuvo una operación.
2. **Consistencia Transaccional:**
   La solicitud solo se marca como `REJECTED` en la base de datos si el agente confirma la cancelación del plan. Si el agente no está disponible, permanece en `PENDING`.
3. **Idempotencia y Prevención de Conflictos:**
   Cualquier intento de rechazar una solicitud previamente aprobada o rechazada retorna `409 Conflict`.
