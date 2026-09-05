Se ha completado la implementación de **[ISSUE-F06-T04] Endpoint de inspección de capacidades: GET /api/v1/agents/{agent_id}/capabilities** y con ello se da por **completada la FASE 06 al 100%**.

### Resumen de Implementación
1. **Controlador de Agentes:** Incorporación del método `capabilities(string $agentId)` en `app/Http/Controllers/Api/AgentController.php`.
2. **Rutas y Autorización:** Registro de `GET /api/v1/agents/{agent_id}/capabilities` y alias `GET /api/agents/{agent_id}/capabilities` bajo middleware `jwt.auth`.
3. **Consulta Delegada y Sincronización:** Invocación a `GET /capabilities` de `jg_agente` mediante `AgentHttpClient` y actualización automática de `capabilities_json` en `dashboard_agents`.
4. **Resiliencia de Red:** Manejo estructurado de caídas de red, rechazo de agentes en estado `PENDING` (400) o inexistentes (404) y captura de timeouts.
5. **Verificación Automatizada:** Suite de pruebas feature en `tests/Feature/AgentCapabilitiesApiTest.php` con mocks de red mediante `Http::fake()`.
6. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.11.0) y documentación técnica en `docs/issues/ISSUE-F06-T04-*`.

/close
