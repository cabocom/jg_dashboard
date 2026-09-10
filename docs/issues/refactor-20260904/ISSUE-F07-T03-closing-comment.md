Se ha completado la implementación de **[ISSUE-F07-T03] Endpoint de aprobación HITL: POST /api/v1/approvals/{approval_id}/approve**.

### Resumen de Implementación
1. **Controlador HITL:** Incorporación de la acción `approve(Request $request, string|int $approvalId)` en `app/Http/Controllers/Api/HitlApprovalController.php`.
2. **Capa de Dominio HITL:** Implementación de `approveApproval(...)` en `app/Services/Hitl/HitlApprovalService.php` con validación de estado pendiente (409 ante conflictos), delegación remota firmada y actualización del modelo.
3. **Comunicación Delegada con jg_agente:** Envío seguro mediante `AgentHttpClient` hacia `POST /executions/{thread_id}/approve` portando contexto del operador, justificación y cabeceras HMAC-SHA256.
4. **Tolerancia a Fallos y Consistencia:** Preservación del estado `PENDING` si la conexión con el agente sufre caídas de red o timeouts (502/503/504), garantizando la posibilidad de reintento.
5. **Auditoría Forense Inmutable:** Registro automático del evento `hitl.approved` en `dashboard_audit_events` con operador, hilo, agente y respuesta del agente.
6. **Rutas y RBAC:** Exposición de `POST /api/v1/approvals/{approval_id}/approve` y el alias raíz bajo `jwt.auth` y `role:operator`.
7. **Verificación Automatizada:** Suite de pruebas feature en `tests/Feature/HitlApprovalApproveApiTest.php` con 100% de la suite en verde.
8. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.14.0) y documentación técnica en `docs/issues/ISSUE-F07-T03-*`.

/close
