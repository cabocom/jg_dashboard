# Contexto Específico de Issue: ISSUE-F07-T03

**Issue ID:** ISSUE-F07-T03  
**Título:** Endpoint de aprobación HITL: POST /api/v1/approvals/{approval_id}/approve  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Versión de Contexto Global Vinculada:** 3.14.0  

---

## 1. Módulos y Clases Involucradas

- `app/Http/Controllers/Api/HitlApprovalController.php`: Acción de resolución `approve` expuesta al plano de control.
- `app/Services/Hitl/HitlApprovalService.php`: Método de orquestación `approveApproval` con validación, delegación remota, persistencia transaccional y auditoría.
- `app/Services/Agents/AgentHttpClient.php`: Cliente HTTP firmado utilizado para enviar la directiva `/approve` a `jg_agente`.
- `app/Models/ApprovalRequest.php`: Actualización de estado (`APPROVED`), resolución y justificación.
- `app/Models/AuditEvent.php`: Emisión inmutable del evento `hitl.approved`.
- `tests/Feature/HitlApprovalApproveApiTest.php`: Pruebas de integración de la acción de aprobación.

---

## 2. Decisiones Arquitectónicas Aplicadas

1. **Garantía de Consistencia Estado-Agente:**
   La solicitud solo se marca como `APPROVED` en la base de datos de `jg_dashboard` una vez que `jg_agente` ha confirmado con éxito la reanudación del grafo. Si el agente falla o no está disponible, la solicitud permanece como `PENDING` para evitar desalineación entre el Control Plane y el Runtime del Agente.
2. **Control de Concurrencia / Idempotencia:**
   El endpoint rechaza con `409 Conflict` cualquier intento posterior de aprobar una solicitud que ya no se encuentre en estado `PENDING`.
3. **Auditoría Forense Exhaustiva:**
   Cada aprobación exitosa registra en `dashboard_audit_events` la identidad del operador humano, su justificación, el hilo de ejecución, el agente y la respuesta devuelta por el runtime del agente.
