Se ha completado la implementación de **[ISSUE-F06-T02] Pasarela para disparar ejecuciones: POST /api/v1/executions/trigger**.

### Resumen de Implementación
1. **Controlador de Pasarela:** Creación de `app/Http/Controllers/Api/ExecutionController.php` con el método `trigger(Request $request)`.
2. **Autorización y RBAC:** Rutas `POST /api/v1/executions/trigger` y alias `POST /api/executions/trigger` protegidas con middlewares `jwt.auth` y `role:operator` (acceso permitido a `operator` y `admin`, 403 Forbidden para `viewer`, 401 Unauthorized sin token).
3. **Resolución y Validación de Agentes:** Soporte para indicar `agent_id` explícito (con verificación de estado `ACTIVE` y rechazo 400/404) o resolución automática del agente activo registrado.
4. **Normalización hacia `AgentInput`:** Generación de `execution_id` y `thread_id` únicos, e inyección de contexto de usuario autenticado (`user_context`) y metadatos.
5. **Gobernanza y Auditoría:**
   - Registro de la acción en `dashboard_audit_events`.
   - Detección de pausas HITL (`requires_approval === true`) y creación preventiva de registros en `dashboard_approval_requests`.
6. **Verificación Automatizada:** Suite de pruebas feature en `tests/Feature/ExecutionTriggerApiTest.php` con mocks de red mediante `Http::fake()`.
7. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.9.0) y documentación técnica en `docs/issues/ISSUE-F06-T02-*`.

/close
