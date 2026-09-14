# Walkthrough: [DASH-SEC-02] Endpoint de Heartbeat sin Firma HMAC Transmite Secreto en Plaintext

## Resumen de Cambios

### 1. `jg_dashboard/app/Http/Middleware/VerifyHmacSignature.php` [NUEVO]
- Middleware que verifica las firmas HMAC-SHA256 en peticiones entrantes de agentes:
  - Valida cabeceras `X-Signature`, `X-Timestamp`, `X-Agent-ID`.
  - Verifica ventana de tolerancia temporal (300 segundos).
  - Carga el agente y verifica que esté en estado `ACTIVE`.
  - Resuelve la clave simétrica con `resolveHmacSecret()`.
  - Valida la firma en tiempo constante mediante `hash_equals`.

### 2. `jg_dashboard/bootstrap/app.php`
- Registro del alias `'agent.hmac' => \App\Http\Middleware\VerifyHmacSignature::class`.

### 3. `jg_dashboard/routes/api.php`
- Aplicación de `middleware('agent.hmac')` en:
  - `POST /api/v1/agents/heartbeat`
  - `POST /api/agents/heartbeat`

### 4. `jg_dashboard/app/Models/Agent.php`
- Implementación de `resolveHmacSecret(): ?string` para obtener el secreto criptográfico desencriptado o configurado en el agente.

### 5. `jg_dashboard/app/Services/Agents/AgentHeartbeatService.php`
- Implementación de `recordHeartbeat(Agent $agent, ?string $status, ?array $metrics): array` para asentar latidos directamente sobre el modelo autenticado.

### 6. `jg_dashboard/app/Http/Controllers/Api/AgentHeartbeatController.php`
- Eliminación de la lectura de credenciales en texto plano (`X-Agent-Secret` y `Authorization: Bearer`).
- Consumo del agente autenticado provisto por el middleware `agent.hmac`.

### 7. `jg_agente/app/core/heartbeat.py`
- Envío de latidos firmados criptográficamente mediante HMAC-SHA256 (`compute_canonical_string` y `compute_hmac_signature`).
- Eliminación definitiva del envío de secretos en texto plano.

### 8. Pruebas Unitarias y de Integración
- `jg_dashboard/tests/Feature/AgentHeartbeatApiTest.php`: 9 tests verificando firma HMAC válida, rechazo de texto plano (CWE-319), rechazo de firmas manipuladas, timestamps expirados y agentes no registrados o pendientes.
- `jg_agente/tests/test_heartbeat_service.py`: verificación de emisión de cabeceras HMAC y ausencia de secretos en texto plano.

---

## Verificación de Pruebas

```bash
# Tests de Heartbeat en jg_dashboard
cd jg_dashboard && ./vendor/bin/pest tests/Feature/AgentHeartbeatApiTest.php
# 9 passed (23 assertions)

# Tests de Heartbeat en jg_agente
cd jg_agente && .venv/bin/pytest tests/test_heartbeat_service.py
# 7 passed in 0.14s

# Suite global de tests
bash check_unit.sh
# 713 tests pasados exitosamente
```
