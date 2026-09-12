Se ha completado la implementación de **[ISSUE-F07-T01] Gestión de solicitudes de aprobación HITL en Dashboard**.

### Resumen de Implementación
1. **Servicio de Dominio HITL:** Creación de `HitlApprovalService` en `app/Services/Hitl/HitlApprovalService.php` para registrar solicitudes de aprobación cuando un agente reporte estado `waiting_approval`.
2. **Estructuración y Enriquecimiento de Datos:** Persistencia del plan propuesto (`proposed_plan_json`), normalización de herramientas involucradas (`tools_involved`) y comandos de compensación (`compensation_commands_json`).
3. **Idempotencia:** Control de unicidad que actualiza solicitudes existentes en estado `PENDING` para el mismo `thread_id` ante nuevos checkpoints, evitando duplicados.
4. **Auditoría Forense:** Registro automático del evento `hitl.requested` en `dashboard_audit_events` asociando usuario iniciador, agente, nivel de riesgo y contexto HTTP.
5. **Integración en Controladores:** Refactorización de `ExecutionController@trigger` para delegar la persistencia de HITL en `HitlApprovalService`.
6. **Verificación Automatizada:** Suite de pruebas unitarias en `tests/Unit/HitlApprovalServiceTest.php` y suite general validada al 100%.
7. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.12.0) y documentación técnica en `docs/issues/ISSUE-F07-T01-*`.

/close
