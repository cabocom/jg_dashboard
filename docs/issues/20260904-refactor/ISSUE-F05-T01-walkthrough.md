# Walkthrough: ISSUE-F05-T01 — Endpoint de recepción de heartbeats: POST /api/v1/agents/heartbeat

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el endpoint de procesamiento de heartbeats y telemetría de agentes en `jg_dashboard`:

1. **Modelo `Agent` ([app/Models/Agent.php](../../../app/Models/Agent.php)):**
   - Incorporado el método `verifyPermanentSecret(string $plainSecret): bool` con comparación en tiempo constante `hash_equals` para contrastar contra `permanent_secret_hash`.

2. **Servicio de Heartbeat ([app/Services/Agents/AgentHeartbeatService.php](../../../app/Services/Agents/AgentHeartbeatService.php)):**
   - Método `processHeartbeat(...)`:
     - Valida la presencia de `agent_id` y `plainSecret`.
     - Verifica existencia del agente, rechaza agentes no activados (`PENDING`) y valida la firma criptográfica del secreto permanente.
     - Actualiza `last_heartbeat_at = now()`, consolida `status = 'ACTIVE'` y persiste el objeto `metrics_json` con las métricas operativas del agente.

3. **Controlador API ([app/Http/Controllers/Api/AgentHeartbeatController.php](../../../app/Http/Controllers/Api/AgentHeartbeatController.php)):**
   - Extracción de `agent_id` desde header `X-Agent-ID` o payload JSON.
   - Extracción de credencial desde `Authorization: Bearer <secret>`, header `X-Agent-Secret` o payload JSON.
   - Validación de esquema de métricas y ejecuciones activas.
   - Retorno de código HTTP `200 OK` con datos actualizados del agente o `401 Unauthorized` ante fallos de autenticación.

4. **Enrutamiento ([routes/api.php](../../../routes/api.php)):**
   - Declaración de `POST /api/v1/agents/heartbeat` y alias `POST /api/agents/heartbeat`.

5. **Suite de Pruebas Unitarias y de Feature:**
   - [tests/Unit/AgentHeartbeatServiceTest.php](../../../tests/Unit/AgentHeartbeatServiceTest.php): 5 pruebas unitarias verificando validación de secreto, actualización de métricas, rechazo de credenciales inválidas y rechazo de agentes en estado PENDING.
   - [tests/Feature/AgentHeartbeatApiTest.php](../../../tests/Feature/AgentHeartbeatApiTest.php): 7 pruebas de feature verificando autenticación por headers, por body JSON, códigos 401 y funcionamiento del alias.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentHeartbeatServiceTest
  ✓ Agent verifyPermanentSecret correctly validates plain secret against SHA-256 hash
  ✓ processHeartbeat updates last_heartbeat_at, status to ACTIVE and records metrics
  ✓ processHeartbeat rejects invalid permanent secret with 401
  ✓ processHeartbeat rejects non-existent agent ID with 401
  ✓ processHeartbeat rejects agent in PENDING status with 401
   PASS  Tests\Unit\AgentModelTest
   PASS  Tests\Unit\AgentRegistrationServiceTest
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AdminAgentApiTest
   PASS  Tests\Feature\AgentHeartbeatApiTest
  ✓ POST /api/v1/agents/heartbeat returns 401 when agent_id or secret is missing
  ✓ POST /api/v1/agents/heartbeat with X-Agent-ID header and Bearer token returns 200 OK and updates agent
  ✓ POST /api/v1/agents/heartbeat with JSON body credentials returns 200 OK
  ✓ POST /api/v1/agents/heartbeat rejects invalid permanent secret with 401
  ✓ POST /api/v1/agents/heartbeat rejects non-existent agent with 401
  ✓ POST /api/v1/agents/heartbeat rejects agent in PENDING status with 401
  ✓ root alias /api/agents/heartbeat functions identically
   PASS  Tests\Feature\AgentRegistrationApiTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\InitAdminCommandTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    114 passed (521 assertions)
  Duration: 2.37s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```
