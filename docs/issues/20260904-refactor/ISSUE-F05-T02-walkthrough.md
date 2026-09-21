# Walkthrough: ISSUE-F05-T02 — Servicio en background de detección de agentes caídos (Health Monitor)

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el monitor de salud y detección de agentes inactivos en `jg_dashboard`:

1. **Modelo `Agent` ([app/Models/Agent.php](../../../app/Models/Agent.php)):**
   - Incorporado el scope `scopeTimedOut(Builder $query, int $timeoutSeconds = 90)` que filtra agentes en estado `ACTIVE` con `last_heartbeat_at` nulo o anterior al umbral configurado.

2. **Servicio Supervisor ([app/Services/Agents/AgentHealthMonitorService.php](../../../app/Services/Agents/AgentHealthMonitorService.php)):**
   - Método `checkAndMarkOfflineAgents(int $timeoutSeconds = 90)`:
     - Identifica agentes caducados.
     - Ejecuta la transición de estado a `OFFLINE`.
     - Registra eventos de auditoría `AGENT_OFFLINE` con metadatos contextuales (`timeout_seconds`, `previous_status`, `last_heartbeat_at`).
     - Devuelve la colección de agentes afectados.

3. **Comando Artisan y Programación ([app/Console/Commands/CheckAgentHealthCommand.php](../../../app/Console/Commands/CheckAgentHealthCommand.php), [routes/console.php](../../../routes/console.php)):**
   - Comando `agents:check-health {--timeout=90}`.
   - Programación en el Scheduler de Laravel para ejecución periódica cada minuto (`everyMinute()`).
   - Script helper ejecutable `scripts/check_health.sh`.

4. **Suite de Pruebas Unitarias y de Feature:**
   - [tests/Unit/AgentHealthMonitorServiceTest.php](../../../tests/Unit/AgentHealthMonitorServiceTest.php): 3 pruebas unitarias verificando la detección de agentes sin heartbeat >90s, retención de agentes sanos y agentes pendientes, y auditoría.
   - [tests/Feature/CheckAgentHealthCommandTest.php](../../../tests/Feature/CheckAgentHealthCommandTest.php): 2 pruebas de feature verificando la salida CLI de Artisan y la actualización en base de datos.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentHealthMonitorServiceTest
  ✓ checkAndMarkOfflineAgents marks agents without heartbeat >90s as OFFLINE and records audit event
  ✓ checkAndMarkOfflineAgents transitions active agents with null heartbeat to OFFLINE
  ✓ checkAndMarkOfflineAgents returns empty collection when all active agents are healthy
   PASS  Tests\Unit\AgentHeartbeatServiceTest
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
   PASS  Tests\Feature\AgentRegistrationApiTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest
   PASS  Tests\Feature\CheckAgentHealthCommandTest
  ✓ agents:check-health identifies inactive agents and marks them OFFLINE via Artisan
  ✓ agents:check-health outputs success message when all active agents are healthy
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\InitAdminCommandTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    119 passed (541 assertions)
  Duration: 2.21s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```
