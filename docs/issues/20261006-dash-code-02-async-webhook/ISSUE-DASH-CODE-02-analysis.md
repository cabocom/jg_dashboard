# Análisis Técnico: [DASH-CODE-02] Dependencia Exclusiva de Polling sin Endpoints de Webhook para Tareas Asíncronas

## 1. Contexto y Problema
Al disparar ejecuciones cognitivas asíncronas (`async=true` o `sync=false`), el Dashboard delegaba la ejecución hacia `jg_agente` retornando `202 Accepted`. Sin embargo:
1. El Dashboard no exponía un endpoint seguro estandarizado de recepción de notificaciones intermedias de estado (*status-update callback*).
2. Todo el seguimiento del ciclo de vida asíncrono dependía exclusivamente de que la consola web o clientes externos ejecutaran *polling* continuo sobre `GET /api/v1/executions/{id}`.
3. Esto ocasionaba:
   - Sobrecarga de peticiones periódicas sobre el Control Plane y el Execution Plane.
   - Retardo en la detección del fin de una ejecución o de una interrupción crítica para aprobación humana (HITL).

## 2. Solución Propuesta
1. **Endpoint de Webhook Autenticado con HMAC:**
   - Crear la ruta `POST /api/v1/internal/executions/{execution_id}/status-update`.
   - Proteger el endpoint con el middleware canónico `agent.hmac` (`VerifyHmacSignature`), exigiendo cabeceras `X-Signature`, `X-Timestamp` (ventana de 300s) y `X-Agent-ID`.
2. **Procesamiento de Actualizaciones de Estado en Tiempo Real:**
   - En `InternalExecutionController@statusUpdate`:
     - Resolver el agente autenticado.
     - Mapear el estado reportado (`in_progress`, `observing`, `reasoning`, `planning`, `executing`, `verifying`, `waiting_approval`, `completed`, `failed`, `cancelled`) a los estados normalizados de `AgentTask`.
     - Sincronizar de forma atómica el registro en `AgentTask` acumulando trazas de pasos cognitivos (`cognitive_steps`), duración y resultados.
     - Registrar de inmediato solicitudes HITL pendientes en `ApprovalRequest` mediante `HitlApprovalService::registerPendingApproval()` cuando el estado indique `WAITING_APPROVAL` o `requires_approval=true`.
     - Registrar evento de auditoría inmutable (`execution.status_update`).
3. **Inyección de URL de Callback en Disparos Asíncronos:**
   - En `ExecutionController@trigger`:
     - Al invocar con `async=true`, inyectar `status_callback_url` y `callback_url` en `parameters` y en `metadata` dirigidos a `jg_agente`.
     - Retornar `status_callback_url` en la respuesta JSON 202 para máxima visibilidad.
   - En `ExecutionController`: añadir método puente `statusUpdate` para consumo directo o proxy unificado.

## 3. Criterios de Aceptación
- [x] Endpoint `POST /api/v1/internal/executions/{execution_id}/status-update` activo y documentado.
- [x] Autenticación HMAC canónica (`agent.hmac`) con verificación anti-replay estricta.
- [x] Sincronización en caliente de `AgentTask` con pasos, duración y estado.
- [x] Registro instantáneo de solicitudes HITL sin requerir polling previo.
- [x] `status_callback_url` inyectada y devuelta en peticiones asíncronas.
- [x] 100% de tests unitarios y de integración pasando.
