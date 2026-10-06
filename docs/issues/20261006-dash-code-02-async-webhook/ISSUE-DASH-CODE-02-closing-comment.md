# Comentario de Cierre: [DASH-CODE-02] Dependencia Exclusiva de Polling sin Endpoints de Webhook para Tareas Asíncronas

Se ha implementado el endpoint de webhook autenticado con HMAC para la notificación proactiva de cambios de estado y finalizaciones en tareas asíncronas, superando la dependencia exclusiva de polling continuo.

### Resumen de Cambios:
1. **Endpoint `POST /api/v1/internal/executions/{execution_id}/status-update`:**
   - Autenticado estrictamente mediante el middleware canónico `agent.hmac` con firmas HMAC-SHA256 y protección anti-replay temporal (ventana de 300 segundos).
2. **Sincronización en Tiempo Real:**
   - Actualización atómica del registro de tareas en `AgentTask` con pasos cognitivos (`cognitive_steps`), duración calculada y resultados parciales/finales.
   - Activación inmediata de solicitudes HITL (`ApprovalRequest`) ante pausas cognitivas que requieran intervención de un operador humano.
   - Registro de trazas de auditoría inmutables (`execution.status_update`).
3. **Inyección de Callback en Modo Asíncrono:**
   - `ExecutionController@trigger` ahora inyecta `status_callback_url` y `callback_url` en los parámetros y metadatos hacia el agente, y la expone en la respuesta HTTP 202 Accepted.
4. **Cobertura de Pruebas:**
   - Cobertura exhaustiva en `tests/Feature/InternalExecutionWebhookTest.php` y `tests/Feature/ExecutionTriggerApiTest.php`.
   - 325 tests pasando sin regresiones en `jg_dashboard`.
