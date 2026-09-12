Se ha completado la implementación de **[ISSUE-F06-T03] Consulta de estado de ejecuciones: GET /api/v1/executions/{agent_id}/{thread_id}**.

### Resumen de Implementación
1. **Controlador de Ejecuciones:** Ampliación de `app/Http/Controllers/Api/ExecutionController.php` con el método `show(Request $request, string $agentId, string $threadId)`.
2. **Rutas y Autorización:** Registro de `GET /api/v1/executions/{agent_id}/{thread_id}` y alias `GET /api/executions/{agent_id}/{thread_id}` bajo middleware `jwt.auth` (accesible para roles `admin`, `operator` y `viewer`).
3. **Aislamiento de Persistencia:** Consulta delegada mediante cliente firmado `AgentHttpClient` al endpoint `GET /executions/{thread_id}` de `jg_agente` con cero conexiones directas a la base de datos del agente.
4. **Estado Unificado y Enriquecido:** Fusión de los datos del checkpointer del agente (pasos, plan, output) con la gobernanza HITL del Dashboard (`dashboard_approval_requests`).
5. **Verificación Automatizada:** Suite de pruebas feature en `tests/Feature/ExecutionStatusApiTest.php` con mocks de red mediante `Http::fake()`.
6. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.10.0) y documentación técnica en `docs/issues/ISSUE-F06-T03-*`.

/close
