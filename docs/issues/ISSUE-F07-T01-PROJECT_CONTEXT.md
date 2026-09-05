# Contexto Específico de Issue: ISSUE-F07-T01

**Issue ID:** ISSUE-F07-T01  
**Título:** Gestión de solicitudes de aprobación HITL en Dashboard  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Versión de Contexto Global Vinculada:** 3.12.0  

---

## 1. Módulos y Clases Involucradas

- `app/Services/Hitl/HitlApprovalService.php`: Servicio de orquestación de aprobaciones HITL, registro y auditoría.
- `app/Http/Controllers/Api/ExecutionController.php`: Integración de la captura HITL al disparar ejecuciones.
- `app/Models/ApprovalRequest.php`: Modelo Eloquent de persistencia sobre `dashboard_approval_requests`.
- `app/Models/AuditEvent.php`: Modelo Eloquent de auditoría sobre `dashboard_audit_events`.
- `tests/Unit/HitlApprovalServiceTest.php`: Cobertura unitaria exhaustiva para el servicio HITL.

---

## 2. Decisiones Arquitectónicas Aplicadas

1. **Centralización en Capa de Servicio:**
   La lógica de negocio para crear, actualizar y auditar solicitudes HITL no reside en los controladores API ni en los recursos de Filament, sino en un servicio de dominio desacoplado (`HitlApprovalService`).
2. **Idempotencia en Checkpoints:**
   Si una ejecución emite actualizaciones sucesivas de su plan mientras se encuentra en estado `PENDING`, el Dashboard actualiza el registro existente para evitar solicitudes duplicadas en la cola del operador.
3. **Auditoría Forense Inmutable:**
   Cada registro o actualización genera una traza de auditoría con tipo de evento `hitl.requested`, almacenando la IP, el usuario operador y el resumen del nivel de riesgo.
