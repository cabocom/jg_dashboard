# Walkthrough: [DASH-CODE-02] Dependencia Exclusiva de Polling sin Endpoints de Webhook para Tareas Asíncronas

## 1. Archivos Modificados e Incorporados

### Rutas de la API (`routes/api.php`)
- Incorporada la ruta de callback interno:
  ```php
  Route::post('/internal/executions/{execution_id}/status-update', [InternalExecutionController::class, 'statusUpdate'])
      ->middleware('agent.hmac')
      ->name('api.v1.internal.executions.status_update');
  ```

### Controladores (`InternalExecutionController.php` y `ExecutionController.php`)
- **`InternalExecutionController.php`:**
  - Implementado el método `statusUpdate(Request $request, string $executionId): JsonResponse`.
  - Verifica identidad del agente autenticado vía `agent.hmac`.
  - Actualiza o crea el modelo `AgentTask` acumulando pasos cognitivos (`cognitive_steps`), nodo actual y resultados parciales o finales.
  - Detecta interrupciones HITL (`WAITING_APPROVAL` o `requires_approval=true`) y registra la solicitud en `ApprovalRequest` en tiempo real vía `HitlApprovalService`.
  - Emite evento de auditoría `execution.status_update`.
- **`ExecutionController.php`:**
  - En `trigger()`: cuando se dispara en modo asíncrono (`async=true`), inyecta `status_callback_url` y `callback_url` en el contrato de entrada `parameters` y en `metadata` hacia el agente, y retorna la URL en la respuesta JSON 202.
  - Implementa `statusUpdate()` como método puente hacia `InternalExecutionController`.

### Pruebas Automatizadas
- **`tests/Feature/InternalExecutionWebhookTest.php`:**
  - Verificación de rechazo 401 ante cabeceras HMAC ausentes.
  - Verificación de rechazo 401 ante firma HMAC inválida.
  - Verificación de rechazo 401 ante timestamp caducado (> 300s).
  - Verificación de actualización en caliente a `PROCESSING` con pasos y nodo actual.
  - Verificación de actualización a `COMPLETED` con cálculo de duración y salida final.
  - Verificación de interrupción HITL registrando solicitud en base de datos.
  - Verificación de rechazo 404 en ruta no versionada (CWE-668).
- **`tests/Feature/ExecutionTriggerApiTest.php`:**
  - Verificación de que peticiones asíncronas devuelven `status_callback_url` hacia el endpoint de actualización.

## 2. Resultados de Validación
- Suite `InternalExecutionWebhookTest`: 14 tests pasados (46 aserciones).
- Suite `ExecutionTriggerApiTest`: 13 tests pasados (51 aserciones).
- Suite total `jg_dashboard`: 325 tests pasados (1513 aserciones).
